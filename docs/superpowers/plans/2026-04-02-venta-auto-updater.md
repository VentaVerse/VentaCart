# Venta Auto-Updater Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One-click admin panel updates for Venta stores — no SSH, no GitHub, no Node.js on production.

**Architecture:** Two components: (1) An update server hosted on existing infrastructure (e.g. NGD server subdomain) that serves version info and release zips via two HTTP endpoints. (2) A Venta client module (controller + view + service) built into every install that checks for updates, downloads zips, extracts, and runs post-update commands. Release zips include pre-built `public/build/` assets so production servers never need Node.js.

**Tech Stack:** Laravel 12, PHP 8.2+, ZipArchive extension, cURL for HTTP. No additional dependencies.

---

## Prerequisites

Before starting, two prep steps are needed:

### Prep A: Commit `public/build/` to the repo

Remove `/public/build` from `.gitignore` and commit the compiled Vite assets. This eliminates the Node.js requirement on production servers.

### Prep B: Add version constant to `config/app.php`

Add `'version' => '1.1.0'` to `config/app.php`. This is the canonical local version that the updater compares against the server's latest version.

---

## File Structure

### Update Server (separate tiny app — hosted on your server)

```
update-server/
  index.php              — Single-file API: /check and /download endpoints
  releases/              — Directory of release zips (e.g. venta-1.2.0.zip)
  releases.json          — Version registry: version, changelog, filename, date
```

### Venta Client (built into every Venta install)

```
app/Http/Controllers/Admin/UpdateController.php   — Check, download, apply updates
app/Services/UpdateService.php                     — Core logic: version check, download, extract, post-update
resources/views/admin/updates/index.blade.php      — Admin UI: current version, check button, update button, log output
config/app.php                                     — Add 'version', 'update_url', 'update_token' keys
.env.example                                       — Add VENTA_UPDATE_URL, VENTA_UPDATE_TOKEN
routes/web.php                                     — Add update routes under admin group
resources/views/layouts/admin.blade.php            — Add "Updates" link to Settings sidebar group
bin/release.sh                                     — Script to package release zips
```

---

## Task 1: Prep — Commit build assets and add version tracking

**Files:**
- Modify: `.gitignore` (remove `/public/build` line)
- Modify: `config/app.php` (add `version`, `update_url`, `update_token` keys)
- Modify: `.env.example` (add update server env vars)

- [ ] **Step 1: Remove `/public/build` from `.gitignore`**

In `.gitignore`, delete the line:
```
/public/build
```

- [ ] **Step 2: Add version and update config to `config/app.php`**

After the `'store_id'` line (~line 18), add:

```php
'version' => '1.1.0',
'update_url' => env('VENTA_UPDATE_URL', ''),
'update_token' => env('VENTA_UPDATE_TOKEN', ''),
```

- [ ] **Step 3: Add update env vars to `.env.example`**

Append to `.env.example`:

```env
VENTA_UPDATE_URL=
VENTA_UPDATE_TOKEN=
```

- [ ] **Step 4: Build assets and commit**

```bash
npm run build
git add -f public/build/ .gitignore config/app.php .env.example
git commit -m "chore: commit build assets, add version tracking for auto-updater"
```

---

## Task 2: Update Server — Single-file API

This is a standalone PHP file deployed to your server (e.g. `https://updates.newgearday.com/`). It is NOT part of the Venta codebase — it lives on your infrastructure.

**Files:**
- Create: `update-server/index.php`
- Create: `update-server/releases.json`
- Create: `update-server/releases/` (empty directory for zips)

- [ ] **Step 1: Create `update-server/releases.json`**

```json
{
    "latest": "1.1.0",
    "releases": [
        {
            "version": "1.1.0",
            "date": "2026-04-02",
            "filename": "venta-1.1.0.zip",
            "changelog": "Initial tracked release with auto-updater support.",
            "php_min": "8.2",
            "size_mb": 25
        }
    ]
}
```

- [ ] **Step 2: Create `update-server/index.php`**

```php
<?php
/**
 * Venta Update Server
 *
 * Endpoints:
 *   GET /check?version=x.x.x  — Returns latest version info + whether update available
 *   GET /download?token=xxx    — Streams the latest release zip
 *
 * Deploy: point a subdomain (e.g. updates.newgearday.com) to this directory.
 */

header('Content-Type: application/json');

$token = 'YOUR_SHARED_TOKEN_HERE'; // Change this after deployment
$releasesDir = __DIR__ . '/releases';
$registryFile = __DIR__ . '/releases.json';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = '/' . ltrim($uri, '/');

if ($uri === '/check' || $uri === '/') {
    handleCheck($registryFile);
} elseif ($uri === '/download') {
    handleDownload($registryFile, $releasesDir, $token);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}

function handleCheck(string $registryFile): void
{
    if (!file_exists($registryFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Registry not found']);
        return;
    }

    $registry = json_decode(file_get_contents($registryFile), true);
    $currentVersion = $_GET['version'] ?? '0.0.0';
    $latest = $registry['latest'] ?? '0.0.0';

    $updateAvailable = version_compare($latest, $currentVersion, '>');

    $latestRelease = null;
    foreach ($registry['releases'] as $r) {
        if ($r['version'] === $latest) {
            $latestRelease = $r;
            break;
        }
    }

    echo json_encode([
        'update_available' => $updateAvailable,
        'current_version' => $currentVersion,
        'latest_version' => $latest,
        'release' => $latestRelease,
    ]);
}

function handleDownload(string $registryFile, string $releasesDir, string $validToken): void
{
    $providedToken = $_GET['token'] ?? '';

    if ($providedToken !== $validToken) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid token']);
        return;
    }

    $registry = json_decode(file_get_contents($registryFile), true);
    $latest = $registry['latest'] ?? null;
    $filename = null;

    foreach ($registry['releases'] as $r) {
        if ($r['version'] === $latest) {
            $filename = $r['filename'];
            break;
        }
    }

    if (!$filename) {
        http_response_code(404);
        echo json_encode(['error' => 'No release found']);
        return;
    }

    $filepath = $releasesDir . '/' . $filename;
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Release file not found']);
        return;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}
```

- [ ] **Step 3: Create empty releases directory and commit**

```bash
mkdir -p update-server/releases
touch update-server/releases/.gitkeep
git add update-server/
git commit -m "feat: add self-hosted update server (2 endpoints: check + download)"
```

---

## Task 3: Release Script — Build zip for distribution

A shell script you run locally to package a release zip.

**Files:**
- Create: `bin/release.sh`

- [ ] **Step 1: Create `bin/release.sh`**

```bash
#!/bin/bash
set -e

VERSION=$(grep "'version'" config/app.php | grep -oP "'[0-9]+\.[0-9]+\.[0-9]+'" | tr -d "'")

if [ -z "$VERSION" ]; then
    echo "Error: Could not read version from config/app.php"
    exit 1
fi

FILENAME="venta-${VERSION}.zip"
echo "Building release: ${FILENAME}"

npm run build

zip -r "${FILENAME}" . \
    -x ".git/*" \
    -x "node_modules/*" \
    -x ".env" \
    -x "storage/logs/*" \
    -x "storage/framework/cache/data/*" \
    -x "storage/framework/sessions/*" \
    -x "storage/framework/views/*" \
    -x "tests/*" \
    -x "update-server/*" \
    -x "docs/*" \
    -x "*.zip" \
    -x ".claude/*" \
    -x "bin/release.sh"

echo ""
echo "Created: ${FILENAME} ($(du -h ${FILENAME} | cut -f1))"
echo ""
echo "Next steps:"
echo "  1. Copy ${FILENAME} to update-server/releases/"
echo "  2. Update update-server/releases.json with version ${VERSION}"
echo "  3. Deploy update server"
```

- [ ] **Step 2: Make executable and commit**

```bash
chmod +x bin/release.sh
git add bin/release.sh
git commit -m "feat: add release packaging script"
```

---

## Task 4: UpdateService — Core update logic

**Files:**
- Create: `app/Services/UpdateService.php`

- [ ] **Step 1: Create `app/Services/UpdateService.php`**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class UpdateService
{
    private string $updateUrl;
    private string $updateToken;

    public function __construct()
    {
        $this->updateUrl = rtrim(config('app.update_url', ''), '/');
        $this->updateToken = config('app.update_token', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->updateUrl) && !empty($this->updateToken);
    }

    public function currentVersion(): string
    {
        return config('app.version', '0.0.0');
    }

    /**
     * Check the update server for a newer version.
     */
    public function checkForUpdate(): array
    {
        $response = Http::timeout(10)->get($this->updateUrl . '/check', [
            'version' => $this->currentVersion(),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Update server returned HTTP ' . $response->status());
        }

        return $response->json();
    }

    /**
     * Download the latest release zip to a temp file.
     */
    public function downloadRelease(): string
    {
        $tempPath = storage_path('app/venta-update.zip');

        $response = Http::timeout(300)->withOptions([
            'sink' => $tempPath,
        ])->get($this->updateUrl . '/download', [
            'token' => $this->updateToken,
        ]);

        if (!$response->successful()) {
            @unlink($tempPath);
            throw new \RuntimeException('Download failed: HTTP ' . $response->status());
        }

        return $tempPath;
    }

    /**
     * Extract the zip over the current installation.
     */
    public function extractRelease(string $zipPath): void
    {
        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw new \RuntimeException('Failed to open zip: error code ' . $result);
        }

        $zip->extractTo(base_path());
        $zip->close();

        @unlink($zipPath);
    }

    /**
     * Run post-update commands: composer install, migrate, clear caches.
     */
    public function runPostUpdate(): array
    {
        $log = [];

        // Composer install (no dev dependencies)
        $composerBin = $this->findComposer();
        if ($composerBin) {
            $result = Process::path(base_path())
                ->timeout(300)
                ->run($composerBin . ' install --no-dev --no-interaction --optimize-autoloader');
            $log[] = [
                'step' => 'composer install',
                'output' => $result->successful() ? $result->output() : $result->errorOutput(),
            ];
        } else {
            $log[] = ['step' => 'composer install', 'output' => 'SKIPPED: composer not found'];
        }

        // Run migrations
        try {
            Artisan::call('migrate', ['--force' => true]);
            $log[] = ['step' => 'migrate', 'output' => trim(Artisan::output()) ?: 'Nothing to migrate.'];
        } catch (\Throwable $e) {
            $log[] = ['step' => 'migrate', 'output' => 'ERROR: ' . $e->getMessage()];
        }

        // Clear all caches
        try {
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('config:clear');
            $log[] = ['step' => 'clear caches', 'output' => 'OK'];
        } catch (\Throwable $e) {
            $log[] = ['step' => 'clear caches', 'output' => 'ERROR: ' . $e->getMessage()];
        }

        return $log;
    }

    /**
     * Full update pipeline: download, extract, post-update.
     */
    public function applyUpdate(): array
    {
        $log = [];

        $zipPath = $this->downloadRelease();
        $log[] = ['step' => 'download', 'output' => 'Downloaded ' . round(filesize($zipPath) / 1024 / 1024, 1) . ' MB'];

        $this->extractRelease($zipPath);
        $log[] = ['step' => 'extract', 'output' => 'Extracted successfully'];

        $postLog = $this->runPostUpdate();
        $log = array_merge($log, $postLog);

        Log::info('Venta auto-update completed', ['log' => $log]);

        return $log;
    }

    private function findComposer(): ?string
    {
        $candidates = [
            base_path('composer.phar'),
            '/usr/local/bin/composer',
            '/usr/bin/composer',
        ];

        // Check PATH
        $result = Process::run('which composer');
        if ($result->successful()) {
            $candidates[] = trim($result->output());
        }

        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return str_ends_with($path, '.phar')
                    ? PHP_BINARY . ' ' . $path
                    : $path;
            }
        }

        return null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/UpdateService.php
git commit -m "feat: UpdateService — check, download, extract, post-update pipeline"
```

---

## Task 5: UpdateController — Admin HTTP endpoints

**Files:**
- Create: `app/Http/Controllers/Admin/UpdateController.php`
- Modify: `routes/web.php` (add routes)

- [ ] **Step 1: Create `app/Http/Controllers/Admin/UpdateController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UpdateService;
use Illuminate\Http\JsonResponse;

class UpdateController extends Controller
{
    public function __construct(
        private UpdateService $updateService
    ) {}

    public function index()
    {
        return view('admin.updates.index', [
            'currentVersion' => $this->updateService->currentVersion(),
            'configured' => $this->updateService->isConfigured(),
        ]);
    }

    public function check(): JsonResponse
    {
        try {
            $result = $this->updateService->checkForUpdate();
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apply(): JsonResponse
    {
        try {
            $log = $this->updateService->applyUpdate();
            return response()->json([
                'success' => true,
                'new_version' => config('app.version'),
                'log' => $log,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
```

- [ ] **Step 2: Add routes to `routes/web.php`**

Inside the admin route group, add:

```php
// Updates
Route::get('updates', [\App\Http\Controllers\Admin\UpdateController::class, 'index'])->name('updates.index');
Route::post('updates/check', [\App\Http\Controllers\Admin\UpdateController::class, 'check'])->name('updates.check');
Route::post('updates/apply', [\App\Http\Controllers\Admin\UpdateController::class, 'apply'])->name('updates.apply');
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Admin/UpdateController.php routes/web.php
git commit -m "feat: UpdateController — admin endpoints for check + apply"
```

---

## Task 6: Admin View — Updates page + sidebar link

**Files:**
- Create: `resources/views/admin/updates/index.blade.php`
- Modify: `resources/views/layouts/admin.blade.php` (add sidebar link)

- [ ] **Step 1: Create the view** (see separate file — too large for inline)

The view contains:
- Current version display with "Check for Updates" button
- "Not configured" warning with `.env` instructions if `VENTA_UPDATE_URL` is empty
- "Update Available" card with version, changelog, "Update Now" button
- "You're running the latest version" message
- Error display
- Update log output (step-by-step results)
- Success message with reload link
- Alpine.js `updateManager()` component handling all API calls

- [ ] **Step 2: Add sidebar link in `resources/views/layouts/admin.blade.php`**

In the Settings group children array (after the `General` link, ~line 92), add:

```php
$link('admin.updates.index', 'Updates', 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3', 'admin.updates*'),
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/admin/updates/index.blade.php resources/views/layouts/admin.blade.php
git commit -m "feat: admin Updates page with check + one-click update UI"
```

---

## Task 7: Integration Test — End-to-end verification

- [ ] **Step 1:** Set `VENTA_UPDATE_URL` and `VENTA_UPDATE_TOKEN` in `.env` on dev
- [ ] **Step 2:** Deploy `update-server/` to a subdomain
- [ ] **Step 3:** Run `bin/release.sh` and copy zip to `update-server/releases/`
- [ ] **Step 4:** Go to Admin > Settings > Updates, click "Check for Updates" — verify "latest version" message
- [ ] **Step 5:** Bump version in `releases.json` to `1.2.0`, check again — verify "Update Available" shows
- [ ] **Step 6:** Click "Update Now" — verify download, extract, migrate, cache clear all succeed
- [ ] **Step 7:** Final commit

---

## Security Notes

- The `/apply` endpoint runs under admin auth middleware — only logged-in admins can trigger updates
- Consider restricting to super-admin role in a future iteration
- The shared update token prevents unauthorized zip downloads
- The zip excludes `.env` so customer config is never overwritten
- Future: per-customer license keys, pre-update backup, rollback capability
