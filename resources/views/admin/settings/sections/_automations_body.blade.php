{{--
    Automations Body — admin/settings/sections/automations.blade.php

    Reskinned onto the design system: x-admin.card/table/badge/button/field, zero
    raw palette classes, v-* tokens throughout (works in both themes).

    The schedule editor and all AJAX calls (toggle / runNow / saveEditor) are the
    same Alpine component as before — only its Blade markup and class names
    changed. The old file had its own fixed-position toast; that's dropped in
    favor of the shell's shared $store.toasts (see resources/js/admin/admin.js),
    which only supports 'success'/'danger' tones — 'info' status messages (e.g.
    "Running…") are mapped to 'success' since there's no neutral tone.
--}}

@php
    $tz = config('app.timezone', 'UTC');
    $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $crontabLine = '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';
@endphp

<div
    class="space-y-6"
    x-data="automationsTab({
        updateUrlTemplate: '{{ route('admin.automations.update', ['automation' => '__ID__']) }}',
        toggleUrlTemplate: '{{ route('admin.automations.toggle', ['automation' => '__ID__']) }}',
        runUrlTemplate:    '{{ route('admin.automations.runNow', ['automation' => '__ID__']) }}',
        csrf: '{{ csrf_token() }}',
    })"
>
    {{-- Crontab hint --}}
    <div class="rounded-v-lg border border-v-border bg-v-sunken px-4 py-3" x-data="{ copied: false }">
        <div class="flex items-start gap-3">
            <x-admin.icon name="info" class="w-4 h-4 text-v-muted mt-0.5 shrink-0" />
            <div class="flex-1 min-w-0">
                <p class="text-xs text-v-secondary mb-2">
                    For the scheduler to fire, this line must live in the server's crontab. Times below are in
                    <span class="font-vmono text-v-text">{{ $tz }}</span>.
                </p>
                <div class="flex items-center gap-2 flex-wrap">
                    <code class="flex-1 min-w-0 block bg-v-surface border border-v-border rounded-v-md px-3 py-2 text-xs font-vmono text-v-text overflow-x-auto whitespace-nowrap">{{ $crontabLine }}</code>
                    <x-admin.button type="button" variant="secondary" size="sm"
                        @click="navigator.clipboard.writeText(@js($crontabLine)).then(() => { copied = true; setTimeout(() => copied = false, 2000); })">
                        <span x-show="!copied">Copy</span>
                        <span x-show="copied" x-cloak class="text-v-accent">Copied</span>
                    </x-admin.button>
                </div>
            </div>
        </div>
    </div>

    {{-- Automations table --}}
    <x-admin.card title="Automations" description="Scheduled background tasks.">
        <x-admin.table>
            <x-slot name="head">
                <x-admin.table.th>Automation</x-admin.table.th>
                <x-admin.table.th>Command</x-admin.table.th>
                <x-admin.table.th>Schedule</x-admin.table.th>
                <x-admin.table.th>Enabled</x-admin.table.th>
                <x-admin.table.th>Last Run</x-admin.table.th>
                <x-admin.table.th align="right">Actions</x-admin.table.th>
            </x-slot>

            @forelse ($automations as $automation)
                @php
                    // Serialize the row payload into a single scalar so the Alpine click handler
                    // can receive it as a literal JS object. Done in @php (not @json mid-HTML)
                    // because Blade's @json compiler can't span multiple lines reliably.
                    $editPayload = json_encode([
                        'id' => $automation->id,
                        'name' => $automation->name,
                        'command' => $automation->command,
                        'schedule_preset' => $automation->schedule_preset,
                        'schedule_params' => $automation->schedule_params ?: (object) [],
                        'cron_expression' => $automation->cron_expression,
                    ], JSON_HEX_APOS | JSON_HEX_QUOT);
                @endphp
                <x-admin.table.row x-data="{ enabled: {{ $automation->enabled ? 'true' : 'false' }}, running: false }">
                    <x-admin.table.cell>
                        <div class="font-medium text-v-text">{{ $automation->name }}</div>
                        <div class="text-xs text-v-muted mt-0.5 max-w-sm">{{ $automation->description }}</div>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        <code class="text-xs font-vmono bg-v-sunken text-v-secondary px-1.5 py-0.5 rounded-v-md whitespace-nowrap">{{ $automation->command }}</code>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        <div class="text-v-secondary">{{ $automation->schedule_description }}</div>
                        <code class="text-[11px] font-vmono text-v-muted mt-0.5 block">{{ $automation->cron_expression }}</code>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        <button type="button"
                                role="switch"
                                :aria-checked="enabled.toString()"
                                @click="toggle({{ $automation->id }}, $event)"
                                :class="enabled ? 'bg-v-accent' : 'bg-v-strong'"
                                class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-v-accent focus:ring-offset-1">
                            <span :class="enabled ? 'translate-x-4' : 'translate-x-0.5'"
                                  class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                        </button>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        @if ($automation->last_run_status === 'success')
                            <x-admin.badge tone="success">Success</x-admin.badge>
                        @elseif ($automation->last_run_status === 'failed')
                            <x-admin.badge tone="danger">Failed</x-admin.badge>
                        @elseif ($automation->last_run_status === 'running')
                            <x-admin.badge tone="warning" class="animate-pulse">Running</x-admin.badge>
                        @else
                            <x-admin.badge>Never run</x-admin.badge>
                        @endif
                        @if ($automation->last_run_at)
                            <div class="text-[11px] text-v-muted mt-1">{{ $automation->last_run_at->timezone($tz)->format('M j, Y H:i') }}</div>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell align="right">
                        <div class="inline-flex items-center gap-2">
                            <x-admin.button type="button" variant="secondary" size="sm" @click='openEditor({!! $editPayload !!})'>
                                Edit
                            </x-admin.button>
                            <span :title="enabled ? '' : 'Enable this automation to run it manually'">
                                <x-admin.button type="button" variant="primary" size="sm"
                                    @click="runNow({{ $automation->id }}, @js($automation->command), $event)"
                                    x-bind:disabled="!enabled || running">
                                    <span x-show="!running">Run Now</span>
                                    <span x-show="running" x-cloak>Running...</span>
                                </x-admin.button>
                            </span>
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-[13px] text-v-muted">No automations configured.</td></tr>
            @endforelse
        </x-admin.table>
    </x-admin.card>

    {{-- ============================ EDIT MODAL ============================ --}}
    <div x-show="editor.open"
         x-cloak
         @keydown.escape.window="closeEditor()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition.opacity>
        <div class="absolute inset-0 bg-black/50" @click="closeEditor()"></div>

        <div class="relative bg-v-surface rounded-v-lg shadow-2xl border border-v-border w-full max-w-lg max-h-[90vh] overflow-y-auto"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0">

            <div class="px-5 py-4 border-b border-v-border flex items-start justify-between">
                <div>
                    <h4 class="text-[15px] font-semibold text-v-text" x-text="editor.name"></h4>
                    <code class="text-xs font-vmono text-v-muted" x-text="editor.command"></code>
                </div>
                <button type="button" @click="closeEditor()" class="text-v-muted hover:text-v-text p-1 -m-1">
                    <x-admin.icon name="x" class="w-5 h-5" />
                </button>
            </div>

            <div class="p-5 space-y-4">
                {{-- Frequency --}}
                <x-admin.field name="schedule_preset" label="Frequency">
                    <x-admin.select name="schedule_preset" x-model="editor.preset" @change="onPresetChange()">
                        <option value="every_n_minutes">Every N Minutes</option>
                        <option value="hourly">Hourly</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </x-admin.select>
                </x-admin.field>

                {{-- Every N Minutes --}}
                <div x-show="editor.preset === 'every_n_minutes'">
                    <x-admin.field name="every_n_minutes" label="Interval">
                        <x-admin.select name="every_n_minutes" x-model.number="editor.params.minute">
                            <template x-for="n in [1, 5, 10, 15, 20, 30]" :key="n">
                                <option :value="n" x-text="'Every ' + n + ' minute' + (n === 1 ? '' : 's')"></option>
                            </template>
                        </x-admin.select>
                    </x-admin.field>
                    <div x-show="editor.preset === 'every_n_minutes' && editor.params.minute === 1"
                         x-cloak
                         class="mt-2 rounded-v-md border border-v-warning bg-[color-mix(in_srgb,var(--warning)_10%,transparent)] p-3 text-xs text-v-warning">
                        <span class="font-medium">Fires every minute.</span>
                        This puts steady load on your server — use sparingly, only for time-sensitive tasks.
                    </div>
                </div>

                {{-- Hourly --}}
                <div x-show="editor.preset === 'hourly'" x-cloak>
                    <x-admin.field name="hourly_minute" label="Minute of the hour (0–59)">
                        <x-admin.input name="hourly_minute" type="number" min="0" max="59" step="1" x-model.number="editor.params.minute" />
                    </x-admin.field>
                </div>

                {{-- Daily --}}
                <div x-show="editor.preset === 'daily'" x-cloak>
                    <x-admin.field name="daily_time" label="Time of day">
                        <x-admin.input name="daily_time" type="time" x-bind:value="formatTime(editor.params.hour, editor.params.minute)"
                            @input="setTimeFromEvent($event)" />
                    </x-admin.field>
                </div>

                {{-- Weekly --}}
                <div x-show="editor.preset === 'weekly'" x-cloak class="grid grid-cols-2 gap-3">
                    <x-admin.field name="weekly_day" label="Day">
                        <x-admin.select name="weekly_day" x-model.number="editor.params.day_of_week">
                            @foreach ($daysOfWeek as $i => $day)
                                <option value="{{ $i }}">{{ $day }}</option>
                            @endforeach
                        </x-admin.select>
                    </x-admin.field>
                    <x-admin.field name="weekly_time" label="Time">
                        <x-admin.input name="weekly_time" type="time" x-bind:value="formatTime(editor.params.hour, editor.params.minute)"
                            @input="setTimeFromEvent($event)" />
                    </x-admin.field>
                </div>

                {{-- Monthly --}}
                <div x-show="editor.preset === 'monthly'" x-cloak class="grid grid-cols-2 gap-3">
                    <x-admin.field name="monthly_day" label="Day of month">
                        <x-admin.select name="monthly_day" x-model.number="editor.params.day_of_month">
                            <template x-for="d in 28" :key="d">
                                <option :value="d" x-text="d"></option>
                            </template>
                        </x-admin.select>
                        <p class="text-[11px] text-v-muted mt-1">Capped at 28 to avoid month-end gaps.</p>
                    </x-admin.field>
                    <x-admin.field name="monthly_time" label="Time">
                        <x-admin.input name="monthly_time" type="time" x-bind:value="formatTime(editor.params.hour, editor.params.minute)"
                            @input="setTimeFromEvent($event)" />
                    </x-admin.field>
                </div>

                {{-- Live preview --}}
                <div class="rounded-v-md border border-v-border bg-v-accent-weak px-3 py-3">
                    <div class="text-[10px] font-semibold text-v-accent uppercase tracking-wider mb-1">Preview</div>
                    <div class="text-[13px] text-v-text" x-text="previewText()"></div>
                    <code class="block mt-1 text-xs font-vmono text-v-secondary" x-text="previewCron()"></code>
                    <div class="text-[11px] text-v-muted mt-1">Runs in <span class="font-vmono">{{ $tz }}</span></div>
                </div>

                {{-- Error --}}
                <div x-show="editor.error" x-cloak class="rounded-v-md border border-v-danger bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] p-3 text-[13px] text-v-danger" x-text="editor.error"></div>
            </div>

            <div class="px-5 py-3 border-t border-v-border flex items-center justify-end gap-2 bg-v-sunken rounded-b-v-lg">
                <x-admin.button type="button" variant="secondary" @click="closeEditor()">Cancel</x-admin.button>
                <x-admin.button type="button" variant="primary" @click="saveEditor()" x-bind:disabled="editor.saving">
                    <span x-show="!editor.saving">Save Schedule</span>
                    <span x-show="editor.saving" x-cloak>Saving...</span>
                </x-admin.button>
            </div>
        </div>
    </div>
</div>

{{-- Alpine component definition --}}
<script>
    window.automationsTab = function (config) {
        return {
            csrf: config.csrf,
            updateUrlTemplate: config.updateUrlTemplate,
            toggleUrlTemplate: config.toggleUrlTemplate,
            runUrlTemplate: config.runUrlTemplate,

            editor: {
                open: false,
                saving: false,
                error: null,
                id: null,
                name: '',
                command: '',
                preset: 'daily',
                params: { hour: 0, minute: 0, day_of_week: 0, day_of_month: 1 },
            },

            // ---------- URL helpers ----------
            urlFor(template, id) {
                return template.replace('__ID__', id);
            },

            // ---------- Toast (routed through the shell's shared toast store) ----------
            showToast(type, title, message = '', output = '') {
                const tone = type === 'error' ? 'danger' : 'success';
                let text = message ? `${title}: ${message}` : title;
                if (output) text += ` — ${output.slice(0, 150)}`;
                window.Alpine.store('toasts').push(text, tone);
            },

            // ---------- Toggle enabled ----------
            async toggle(id, evt) {
                // Optimistic flip via Alpine reactive: the button's Alpine scope
                // (the table row) exposes `enabled` directly.
                const scope = Alpine.$data(evt.currentTarget.closest('[x-data]'));
                const prev = scope.enabled;
                scope.enabled = !prev;

                try {
                    const res = await fetch(this.urlFor(this.toggleUrlTemplate, id), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrf,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Toggle failed');
                    scope.enabled = !!data.enabled;
                } catch (e) {
                    scope.enabled = prev; // rollback
                    this.showToast('error', 'Could not update', e.message || 'Request failed');
                }
            },

            // ---------- Run Now ----------
            async runNow(id, command, evt) {
                if (!window.confirm('Run `' + command + '` now?')) return;

                const scope = Alpine.$data(evt.currentTarget.closest('[x-data]'));
                scope.running = true;
                this.showToast('info', 'Running ' + command + '…', 'This can take up to 5 minutes.');

                try {
                    const res = await fetch(this.urlFor(this.runUrlTemplate, id), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrf,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) {
                        const msg = data.message || ('HTTP ' + res.status);
                        this.showToast('error', command + ' failed', msg, data.output || '');
                    } else {
                        this.showToast('success', command + ' completed', data.message || '', data.output || '');
                    }
                } catch (e) {
                    this.showToast('error', 'Run failed', e.message || 'Network error');
                } finally {
                    scope.running = false;
                }
            },

            // ---------- Editor ----------
            openEditor(automation) {
                this.editor.id = automation.id;
                this.editor.name = automation.name;
                this.editor.command = automation.command;
                this.editor.preset = automation.schedule_preset;
                this.editor.error = null;

                // Normalize params with sensible defaults so switching presets works cleanly.
                const p = automation.schedule_params || {};
                this.editor.params = {
                    minute: typeof p.minute === 'number' ? p.minute : (automation.schedule_preset === 'every_n_minutes' ? 15 : 0),
                    hour: typeof p.hour === 'number' ? p.hour : 0,
                    day_of_week: typeof p.day_of_week === 'number' ? p.day_of_week : 0,
                    day_of_month: typeof p.day_of_month === 'number' ? p.day_of_month : 1,
                };
                this.editor.open = true;
            },

            closeEditor() {
                this.editor.open = false;
                this.editor.error = null;
            },

            onPresetChange() {
                // Snap `minute` to a valid preset value when switching to every_n_minutes.
                if (this.editor.preset === 'every_n_minutes') {
                    const allowed = [1, 5, 10, 15, 20, 30];
                    if (!allowed.includes(this.editor.params.minute)) {
                        this.editor.params.minute = 15;
                    }
                }
            },

            // ---------- Time input helpers ----------
            formatTime(h, m) {
                const pad = (n) => String(n).padStart(2, '0');
                return pad(h || 0) + ':' + pad(m || 0);
            },

            setTimeFromEvent(evt) {
                const v = evt.target.value || '00:00';
                const [h, m] = v.split(':').map(Number);
                this.editor.params.hour = isNaN(h) ? 0 : h;
                this.editor.params.minute = isNaN(m) ? 0 : m;
            },

            // ---------- Live preview ----------
            previewText() {
                const p = this.editor.params;
                const pad = (n) => String(n).padStart(2, '0');
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                switch (this.editor.preset) {
                    case 'every_n_minutes':
                        return p.minute === 1
                            ? 'Runs every minute.'
                            : 'Runs every ' + p.minute + ' minutes.';
                    case 'hourly':
                        return 'Runs hourly at minute ' + p.minute + '.';
                    case 'daily':
                        return 'Runs daily at ' + pad(p.hour) + ':' + pad(p.minute) + '.';
                    case 'weekly':
                        return 'Runs every ' + days[p.day_of_week] + ' at ' + pad(p.hour) + ':' + pad(p.minute) + '.';
                    case 'monthly':
                        return 'Runs on day ' + p.day_of_month + ' of each month at ' + pad(p.hour) + ':' + pad(p.minute) + '.';
                }
                return '';
            },

            previewCron() {
                const p = this.editor.params;
                switch (this.editor.preset) {
                    case 'every_n_minutes':
                        return p.minute === 1 ? '* * * * *' : '*/' + p.minute + ' * * * *';
                    case 'hourly':
                        return p.minute + ' * * * *';
                    case 'daily':
                        return p.minute + ' ' + p.hour + ' * * *';
                    case 'weekly':
                        return p.minute + ' ' + p.hour + ' * * ' + p.day_of_week;
                    case 'monthly':
                        return p.minute + ' ' + p.hour + ' ' + p.day_of_month + ' * *';
                }
                return '';
            },

            // ---------- Save ----------
            async saveEditor() {
                this.editor.saving = true;
                this.editor.error = null;

                // Build params payload with only the keys this preset needs — the server
                // ignores extras but this keeps the request clean.
                const p = this.editor.params;
                let params = {};
                switch (this.editor.preset) {
                    case 'every_n_minutes': params = { minute: p.minute }; break;
                    case 'hourly':          params = { minute: p.minute }; break;
                    case 'daily':           params = { hour: p.hour, minute: p.minute }; break;
                    case 'weekly':          params = { day_of_week: p.day_of_week, hour: p.hour, minute: p.minute }; break;
                    case 'monthly':         params = { day_of_month: p.day_of_month, hour: p.hour, minute: p.minute }; break;
                }

                try {
                    const res = await fetch(this.urlFor(this.updateUrlTemplate, this.editor.id), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrf,
                            'X-HTTP-Method-Override': 'PATCH',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            _method: 'PATCH',
                            schedule_preset: this.editor.preset,
                            schedule_params: params,
                        }),
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) {
                        this.editor.error = data.message || ('Save failed (HTTP ' + res.status + ')');
                        return;
                    }

                    this.editor.open = false;
                    this.showToast('success', 'Schedule updated', this.editor.name);
                    // Reload so the table reflects the new schedule + cron expression exactly
                    // as the server rendered them. Keeps UI and server in lock-step.
                    setTimeout(() => window.location.reload(), 600);
                } catch (e) {
                    this.editor.error = e.message || 'Network error';
                } finally {
                    this.editor.saving = false;
                }
            },
        };
    };
</script>
