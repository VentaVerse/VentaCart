# Automations Refactor — Universal Scheduler with Admin-Editable Schedules

> **For agentic workers:** Steps use checkbox (`- [ ]`) syntax for tracking. Execute top-to-bottom; do not skip database tasks.

**Goal:** Replace the read-only "Cron Jobs" settings tab with a functional "Automations" admin UI where each scheduled task can be edited (frequency + time), enabled/disabled, and manually triggered. All default-Venta scheduled tasks run via a single universal `schedule:run` heartbeat, driven by rows in a new `automations` DB table. DPH shipment polling is pulled out of Laravel's scheduler entirely and given its own raw crontab line (treated as a site-specific module, not a default Venta feature).

**Architecture:**
- New `automations` table holds one row per scheduled task: name, command, preset+params, derived `cron_expression`, enabled flag, and last-run telemetry.
- `routes/console.php` dynamically reads enabled rows and calls `Schedule::command(...)->cron(...)` for each.
- Admin UI (Settings > Automations tab) lets admin edit schedule via dropdown+params, toggle enabled, and trigger "Run Now".
- Scheduler `->onSuccess`/`->onFailure` callbacks update `last_run_at` and `last_run_status` automatically (commands themselves untouched).
- DPH schedule removed from `routes/console.php` entirely; user adds it as a separate raw crontab line.

**Tech Stack:** Laravel 12, MySQL, Blade + Tailwind CSS, Alpine.js

---

## Final State

**System crontab (user applies manually after deploy):**
```
* * * * * cd /home/ngdadm/web/newgearday.com/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/bin/php /home/ngdadm/web/newgearday.com/public_html/artisan dph:fetch-statuses >> /dev/null 2>&1
```
**Deleted from crontab:** `seo:generate` (monthly), `cart:notify` (hourly) — both now DB-managed.

**Seven default Venta automations (seeded):**
| Key | Name | Command | Default Schedule |
|---|---|---|---|
| `currency_update` | Currency Exchange Rates | `currency:update` | Daily at 16:00 |
| `stock_notify` | Back-in-Stock Notifications | `stock:notify` | Every 15 minutes |
| `review_send` | Review Request Emails | `review:send` | Daily at 10:00 |
| `seo_generate` | SEO Suggestions (Claude AI) | `seo:generate` | Monthly on 1st at 03:00 |
| `sitemap_generate` | Sitemap Generation | `sitemap:generate` | Daily at 04:00 |
| `stock_check_low` | Low Stock Alerts | `stock:check-low` | Daily at 08:00 |
| `cart_notify` | Abandoned Cart Notifications | `cart:notify` | Hourly at :00 |

---

## File Structure

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `database/migrations/2026_04_16_000001_create_automations_table.php` | Schema |
| Create | `app/Models/Automation.php` | Eloquent model + cron translation + scope |
| Create | `database/seeders/AutomationSeeder.php` | Seed the 7 default automations |
| Create | `app/Services/CronExpressionBuilder.php` | Translate preset+params → cron string |
| Create | `app/Http/Controllers/Admin/AutomationController.php` | Admin CRUD + runNow |
| Create | `app/Http/Requests/Admin/AutomationUpdateRequest.php` | Validate schedule params |
| Create | `resources/views/admin/settings/_automations_tab.blade.php` | Tab markup (extracted partial) |
| Modify | `routes/console.php` | Dynamic Schedule::command loop from DB; remove 6 hardcoded lines; keep DPH outside loop as commented site-specific registration *(then remove DPH in task 6)* |
| Modify | `routes/web.php` | Add `/admin/automations/*` routes |
| Modify | `resources/views/admin/settings/index.blade.php` | Rename tab "Cron Jobs" → "Automations"; replace static table with `@include('admin.settings._automations_tab')` |
| Modify | `database/seeders/DatabaseSeeder.php` | Register AutomationSeeder |
| Audit | `app/Http/Controllers/Admin/AbandonedCartController.php` + views | Scrub any "add this to crontab" instructions |

---

### Task 1: Migration, Model, Cron Builder

**Files:**
- Create: `database/migrations/2026_04_16_000001_create_automations_table.php`
- Create: `app/Models/Automation.php`
- Create: `app/Services/CronExpressionBuilder.php`

- [ ] **Step 1.1: Create migration**

```php
Schema::create('automations', function (Blueprint $table) {
    $table->id();
    $table->string('key', 64)->unique();         // 'stock_notify', 'cart_notify', etc.
    $table->string('name', 128);                 // 'Back-in-Stock Notifications'
    $table->string('command', 128);              // 'stock:notify'
    $table->text('description')->nullable();     // shown as help text in UI
    $table->string('schedule_preset', 32);       // 'every_n_minutes' | 'hourly' | 'daily' | 'weekly' | 'monthly'
    $table->json('schedule_params');             // {'minute': 15} etc.
    $table->string('cron_expression', 64);       // derived, canonical for Laravel scheduler
    $table->boolean('enabled')->default(true);
    $table->timestamp('last_run_at')->nullable();
    $table->string('last_run_status', 16)->nullable();  // 'success' | 'failed' | 'running'
    $table->text('last_run_output')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 1.2: Create `CronExpressionBuilder` service**

Pure translator class with one static method `build(string $preset, array $params): string`. Handles the 5 presets:

| Preset | Required params | Cron format |
|---|---|---|
| `every_n_minutes` | `minute` (int, one of [1,5,10,15,20,30]) | `*/N * * * *` |
| `hourly` | `minute` (0–59) | `{minute} * * * *` |
| `daily` | `hour` (0–23), `minute` (0–59) | `{minute} {hour} * * *` |
| `weekly` | `day_of_week` (0–6, Sun=0), `hour`, `minute` | `{minute} {hour} * * {dow}` |
| `monthly` | `day_of_month` (1–28), `hour`, `minute` | `{minute} {hour} {dom} * *` |

Must validate inputs and throw `InvalidArgumentException` for invalid combos. Also provide a `describe(string $preset, array $params): string` method that returns human-readable text like "Daily at 08:00" for display in the admin table.

- [ ] **Step 1.3: Create `Automation` model**

```php
class Automation extends Model {
    protected $fillable = ['key', 'name', 'command', 'description',
        'schedule_preset', 'schedule_params', 'cron_expression', 'enabled',
        'last_run_at', 'last_run_status', 'last_run_output'];
    protected $casts = [
        'schedule_params' => 'array',
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
    ];
    public function scopeEnabled($q) { return $q->where('enabled', true); }
    public function getScheduleDescriptionAttribute(): string {
        return CronExpressionBuilder::describe($this->schedule_preset, $this->schedule_params);
    }
    public function recordRun(string $status, ?string $output = null): void {
        $this->update([
            'last_run_at' => now(),
            'last_run_status' => $status,
            'last_run_output' => $output ? Str::limit($output, 2000) : null,
        ]);
    }
}
```

---

### Task 2: Seeder

**Files:**
- Create: `database/seeders/AutomationSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 2.1: Seed the 7 automations**

Use `updateOrCreate(['key' => ...])` so re-running the seeder is safe. Example row:

```php
Automation::updateOrCreate(['key' => 'stock_notify'], [
    'name' => 'Back-in-Stock Notifications',
    'command' => 'stock:notify',
    'description' => 'Emails customers when products they subscribed to become available.',
    'schedule_preset' => 'every_n_minutes',
    'schedule_params' => ['minute' => 15],
    'cron_expression' => '*/15 * * * *',
    'enabled' => true,
]);
```

All 7 rows per the table in "Final State". Use `CronExpressionBuilder::build()` to compute `cron_expression` at seed time — never hardcode it.

- [ ] **Step 2.2: Register seeder** in `DatabaseSeeder::run()`.

- [ ] **Step 2.3: Run seeder manually** after migration with `php artisan db:seed --class=AutomationSeeder`.

---

### Task 3: Refactor `routes/console.php`

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 3.1: Replace the 6 hardcoded `Schedule::command` lines** with a DB-driven loop.

New structure:

```php
use App\Models\Automation;
use Illuminate\Support\Facades\Schedule;

// ==========================================
// Default Venta automations — managed via admin UI (Settings > Automations)
// Schedules are stored in the `automations` DB table.
// ==========================================
try {
    Automation::enabled()->get()->each(function (Automation $automation) {
        Schedule::command($automation->command)
            ->cron($automation->cron_expression)
            ->onSuccess(fn () => $automation->recordRun('success'))
            ->onFailure(fn () => $automation->recordRun('failed'))
            ->withoutOverlapping();
    });
} catch (\Throwable $e) {
    \Log::error('Automation scheduler registration failed: ' . $e->getMessage());
}

// ==========================================
// Site-specific module registrations (NOT default Venta features)
// These are hardcoded here and not managed via the admin UI.
// NOTE: DPH is handled via a separate raw crontab line — see INSTALL.md.
// ==========================================
```

- [ ] **Step 3.2: DELETE** the 6 old lines:
  - `Schedule::command('currency:update')->dailyAt('16:00');`
  - `Schedule::command('stock:notify')->everyFifteenMinutes();`
  - `Schedule::command('review:send')->dailyAt('10:00');`
  - `Schedule::command('sitemap:generate')->dailyAt('04:00');`
  - `Schedule::command('stock:check-low')->dailyAt('08:00');`
  - `Schedule::command('dph:fetch-statuses')->everyMinute();`  ← DPH removed per decision #2

- [ ] **Step 3.3: Verify** with `php artisan schedule:list` — should show only the 7 DB-sourced rows (minus any disabled), no DPH.

- [ ] **Step 3.4: Handle DB-not-yet-migrated case gracefully.** The `try/catch` above prevents scheduler crashes during `php artisan migrate --seed` on a fresh install when the `automations` table doesn't exist yet. Confirm artisan commands still work when the table is missing.

---

### Task 4: Admin Controller + Routes

**Files:**
- Create: `app/Http/Controllers/Admin/AutomationController.php`
- Create: `app/Http/Requests/Admin/AutomationUpdateRequest.php`
- Modify: `routes/web.php`

- [ ] **Step 4.1: Add routes** (inside the admin auth group):

```php
Route::get('/admin/automations', [AutomationController::class, 'index'])->name('admin.automations.index');
Route::patch('/admin/automations/{automation}', [AutomationController::class, 'update'])->name('admin.automations.update');
Route::post('/admin/automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('admin.automations.toggle');
Route::post('/admin/automations/{automation}/run', [AutomationController::class, 'runNow'])->name('admin.automations.runNow');
```

> Even though the tab lives inside Settings, give automations their own routes so JS actions (toggle, runNow) don't need to round-trip through the settings index form.

- [ ] **Step 4.2: `AutomationUpdateRequest`**

Validates: `schedule_preset` in allowed list; `schedule_params` structure per preset; `enabled` boolean.

- [ ] **Step 4.3: `AutomationController`**

Methods:
- `index()` — lists all automations (passes to settings tab partial)
- `update(AutomationUpdateRequest, Automation)` — updates preset + params; recomputes `cron_expression` via `CronExpressionBuilder::build()`; returns JSON or redirect back
- `toggle(Automation)` — flips `enabled`; returns JSON
- `runNow(Automation)` — dispatches `Artisan::call($automation->command)` in a **detached background process** so HTTP doesn't block on long commands. On success, mark `last_run_status = 'running'` immediately; scheduler callbacks will update it when the process exits.

Implementation note for `runNow`: use `Symfony\Component\Process\Process::start()` with the full `php artisan {command}` path. Don't use `Artisan::queue()` — no queue workers configured.

---

### Task 5: Admin UI (Automations Tab)

**Files:**
- Create: `resources/views/admin/settings/_automations_tab.blade.php`
- Modify: `resources/views/admin/settings/index.blade.php`

- [ ] **Step 5.1: Rename tab**

In `resources/views/admin/settings/index.blade.php` line 16:
```php
['id' => 'cron', 'label' => 'Cron Jobs'],  // OLD
['id' => 'automations', 'label' => 'Automations'],  // NEW
```

Update `x-show="activeTab === 'cron'"` → `x-show="activeTab === 'automations'"` at line 521.

- [ ] **Step 5.2: Replace hardcoded `$cronJobs` array** (lines 537–560) with `@include('admin.settings._automations_tab', ['automations' => $automations])`.

Pass `$automations` from the SettingsController's `index()` method (fetch via `Automation::all()`).

- [ ] **Step 5.3: Build the partial**

Table columns: **Name** | **Command** | **Schedule (human readable)** | **Enabled** (toggle) | **Last Run** (timestamp + colored status) | **Actions** (Edit schedule / Run Now).

Include the `schedule:run` crontab instruction at the top (same text as the old tab, reminder for sysadmins).

Edit button opens an Alpine.js modal with:
- Frequency dropdown: Every N Minutes / Hourly / Daily / Weekly / Monthly
- Conditional params based on selection (interval dropdown for N-minutes; hour+minute time picker for daily; day-of-week for weekly; day-of-month for monthly)
- Live preview: "This will run: Daily at 08:00" + `0 8 * * *`
- Save (PATCH) / Cancel

Enabled toggle is an inline switch that POSTs immediately (no modal).

"Run Now" button shows confirmation prompt ("Run `stock:notify` now? It will fire even though its next scheduled run is in 10 min."), then POSTs to the runNow endpoint, shows a toast on success.

---

### Task 6: DPH Decoupling

**Files:**
- (Already deleted in Task 3.2) `Schedule::command('dph:fetch-statuses')` line from `routes/console.php`
- Modify: `INSTALL.md` (add DPH crontab line documentation)

- [ ] **Step 6.1: Verify DPH is gone** from `php artisan schedule:list`.

- [ ] **Step 6.2: Document the DPH crontab line** in `INSTALL.md` under a "DPH Module (Optional)" section:

```
# DeliveryPH (DPH) shipment status polling
# Only needed if the DPH module is active on this site.
* * * * * /usr/bin/php /path/to/public_html/artisan dph:fetch-statuses >> /dev/null 2>&1
```

---

### Task 7: Abandoned Cart Cleanup

**Files:**
- Audit: `app/Http/Controllers/Admin/AbandonedCartController.php`
- Audit: all views under `resources/views/admin/abandoned-carts/` (or wherever its admin pages live)

- [ ] **Step 7.1: Grep for cron references**

Search for: `cart:notify`, `crontab`, `cron job`, `0 * * * *` within the Abandoned Cart admin files. Remove any help text that says "add this to your server's crontab" or similar. Replace with a small note linking to Settings > Automations if appropriate.

- [ ] **Step 7.2: Verify** the abandoned-cart admin pages no longer reference cron setup instructions.

---

### Task 8: Manual Deployment Steps (user does these)

- [ ] **Step 8.1: Run migrations + seeder**
  ```
  php artisan migrate
  php artisan db:seed --class=AutomationSeeder
  php artisan view:clear
  ```

- [ ] **Step 8.2: Edit crontab** (`crontab -e`) — final content:

```
MAILTO=newgearday@gmail.com
CONTENT_TYPE="text/plain; charset=utf-8"
* * * * * cd /home/ngdadm/web/newgearday.com/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/bin/php /home/ngdadm/web/newgearday.com/public_html/artisan dph:fetch-statuses >> /dev/null 2>&1
```

Delete the old `seo:generate` and `cart:notify` lines.

- [ ] **Step 8.3: Smoke test**
  - Visit `/admin/automations` — confirm all 7 rows appear
  - Click "Run Now" on Back-in-Stock Notifications — verify the 19 pending subscribers get emailed (or at least that `last_run_at` updates)
  - Edit a schedule (e.g., change Currency Update to Daily at 17:00) — verify `cron_expression` updates in DB and `php artisan schedule:list` reflects the new time
  - Wait 2 minutes and confirm `last_run_at` advances on `dph:fetch-statuses` (via its separate crontab line) and on any Automation that's due

---

## Validation Checklist (agent confirms before marking plan complete)

- [ ] `php artisan migrate` runs cleanly
- [ ] `php artisan db:seed --class=AutomationSeeder` creates 7 rows
- [ ] `php artisan schedule:list` shows exactly the enabled automations (no DPH)
- [ ] `/admin/automations` renders with all 7 rows and correct human-readable schedules
- [ ] Editing a schedule via UI updates `cron_expression` and is reflected in `schedule:list`
- [ ] Toggle enabled/disabled works and disabled rows disappear from `schedule:list`
- [ ] "Run Now" successfully invokes a command in the background
- [ ] `last_run_at` / `last_run_status` update when scheduler actually runs a task
- [ ] No references to cron setup remain in Abandoned Cart admin pages
- [ ] `php artisan test` (if tests exist) passes
- [ ] `npm run build` succeeds (if any JS/CSS touched)

---

## Risks / Open Questions

1. **Scheduler fails on fresh install before migration**: mitigated by `try/catch` in `routes/console.php` — but confirm `php artisan migrate` itself still works when `automations` table doesn't exist (it should — migrate doesn't invoke `schedule:run`).
2. **"Run Now" on a long command**: detached process avoids HTTP timeout, but if the command crashes, the user won't see the error in the browser. Last-run status will show 'failed' via `->onFailure` callback. Consider logging stderr to `last_run_output`.
3. **Multi-store scoping**: current admin settings appear single-store. Automations table has no `store_id`. If Venta later goes multi-store, this table will need that column.
4. **Cron expression validation**: Laravel uses `dragonmantank/cron-expression` under the hood. Our builder computes clean expressions, but if someone bypasses the UI and sets a bad expression via DB, the `try/catch` prevents scheduler crashes but silently skips everything. Acceptable for MVP; monitor logs.
