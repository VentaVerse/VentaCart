@php
    $tz = config('app.timezone', 'UTC');
    $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $crontabLine = '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';
@endphp
<x-admin3.settings-shell title="Automations" active="automations">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Automations</h1>
            <p class="v3-head__standing">Scheduled jobs, their timetables, and manual runs. Times are in {{ $tz }}.</p>
        </div>
    </div>

    <div class="v3-settle"
         x-data="automationsTab({
             updateUrlTemplate: @js(route('admin.automations.update', ['automation' => '__ID__'])),
             toggleUrlTemplate: @js(route('admin.automations.toggle', ['automation' => '__ID__'])),
             runUrlTemplate: @js(route('admin.automations.runNow', ['automation' => '__ID__'])),
         })">
        {{-- None of the schedules below fire at all unless this line is in
             the server's crontab. --}}
        <div class="v3-notice"
             x-data="{ copied: false, async copy() {
                 if (!await window.v3Copy($refs.crontab.value)) return;
                 this.copied = true;
                 setTimeout(() => this.copied = false, 2000);
             } }">
            <p class="v3-notice__title">Server crontab
                <x-admin3.help text="The schedules below only fire while this line is installed on the server." /></p>
            <div class="v3-noticerow">
                <input type="text" x-ref="crontab" readonly value="{{ $crontabLine }}" onclick="this.select()"
                       aria-label="Crontab line" class="v3-control v3-mono">
                <button type="button" class="v3-btn v3-btn--secondary" @click="copy()" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
            </div>
        </div>

        <x-admin3.panel title="Automations" flush class="v3-gap">
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Automation</th><th>Command</th><th>Schedule</th><th>Enabled</th><th>Last run</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($automations as $automation)
                            @php
                                // Built as a plain PHP array and handed to @js():
                                // the one path that escapes correctly for a JS
                                // expression position.
                                $editPayload = [
                                    'id' => $automation->id,
                                    'name' => $automation->name,
                                    'command' => $automation->command,
                                    'schedule_preset' => $automation->schedule_preset,
                                    'schedule_params' => $automation->schedule_params ?: (object) [],
                                    'cron_expression' => $automation->cron_expression,
                                ];
                            @endphp
                            <tr x-data="{ enabled: @js((bool) $automation->enabled), running: false }" :class="{ 'v3-row-dim': !enabled }">
                                <td>
                                    {{ $automation->name }}
                                    @if ($automation->description)
                                        <x-admin3.help :text="$automation->description" />
                                    @endif
                                </td>
                                <td><code class="v3-mono">{{ $automation->command }}</code></td>
                                <td>
                                    <div>{{ $automation->schedule_description }}</div>
                                    <code class="v3-mono v3-cell-quiet">{{ $automation->cron_expression }}</code>
                                </td>
                                <td>
                                    <button type="button" class="v3-toggle v3-toggle--btn" role="switch"
                                            :aria-checked="enabled ? 'true' : 'false'"
                                            aria-label="{{ $automation->name }} enabled"
                                            @click="toggle({{ $automation->id }}, $event)">
                                        <span class="v3-toggle__track" aria-hidden="true"></span>
                                    </button>
                                </td>
                                <td>
                                    @if ($automation->last_run_status === 'success')
                                        <span class="v3-badge v3-badge--good">Success</span>
                                    @elseif ($automation->last_run_status === 'failed')
                                        <span class="v3-badge v3-badge--bad">Failed</span>
                                    @elseif ($automation->last_run_status === 'running')
                                        <span class="v3-badge v3-badge--warn">Running</span>
                                    @else
                                        <span class="v3-badge v3-badge--quiet">Never run</span>
                                    @endif
                                    @if ($automation->last_run_at)
                                        <div class="v3-cell-quiet">{{ $automation->last_run_at->timezone($tz)->format('M j, Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="v3-cell-act">
                                    <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="openEditor(@js($editPayload))">Edit</button>
                                    <button type="button" class="v3-btn v3-btn--primary v3-btn--sm"
                                            :disabled="!enabled || running"
                                            :title="enabled ? '' : 'Enable this automation to run it manually'"
                                            @click="askRun({{ $automation->id }}, @js($automation->command), $event)"
                                            x-text="running ? 'Running…' : 'Run now'">Run now</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v3-empty"><p class="v3-empty__title">No automations configured</p></div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        {{-- Schedule editor. Hand-rolled on the v3 modal primitives because
             its body reads automationsTab's own editor state. --}}
        <template x-if="editor.open">
            <div class="v3-overlay" @click.self="closeEditor()" @keydown.escape.window="closeEditor()">
                <div class="v3-modal" role="dialog" aria-modal="true" aria-labelledby="automation-editor-title">
                    <h2 class="v3-modal__title" id="automation-editor-title" x-text="editor.name"></h2>
                    <p class="v3-modal__body v3-mono" x-text="editor.command"></p>

                    <x-admin3.field name="schedule_preset" label="Frequency">
                        <select class="v3-control" x-model="editor.preset" @change="onPresetChange()">
                            <option value="every_n_minutes">Every N minutes</option>
                            <option value="hourly">Hourly</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </x-admin3.field>

                    <div class="v3-gap" x-show="editor.preset === 'every_n_minutes'">
                        <x-admin3.field name="every_n_minutes" label="Interval">
                            <select class="v3-control" x-model.number="editor.params.minute">
                                <template x-for="n in [1, 5, 10, 15, 20, 30]" :key="n">
                                    <option :value="n" x-text="'Every ' + n + ' minute' + (n === 1 ? '' : 's')"></option>
                                </template>
                            </select>
                        </x-admin3.field>
                        <p class="v3-field__hint" x-show="editor.params.minute === 1" x-cloak>
                            Fires every minute and puts steady load on the server. Only for time-sensitive tasks.
                        </p>
                    </div>

                    <div class="v3-gap" x-show="editor.preset === 'hourly'" x-cloak>
                        <x-admin3.field name="hourly_minute" label="Minute of the hour (0-59)">
                            <input type="number" min="0" max="59" step="1" class="v3-control v3-mono" x-model.number="editor.params.minute">
                        </x-admin3.field>
                    </div>

                    <div class="v3-gap" x-show="editor.preset === 'daily'" x-cloak>
                        <x-admin3.field name="daily_time" label="Time of day">
                            <input type="time" class="v3-control v3-mono" :value="formatTime(editor.params.hour, editor.params.minute)"
                                   @input="setTimeFromEvent($event)">
                        </x-admin3.field>
                    </div>

                    <div class="v3-gap v3-grid v3-grid--2" x-show="editor.preset === 'weekly'" x-cloak>
                        <x-admin3.field name="weekly_day" label="Day">
                            <select class="v3-control" x-model.number="editor.params.day_of_week">
                                @foreach ($daysOfWeek as $i => $day)
                                    <option value="{{ $i }}">{{ $day }}</option>
                                @endforeach
                            </select>
                        </x-admin3.field>
                        <x-admin3.field name="weekly_time" label="Time">
                            <input type="time" class="v3-control v3-mono" :value="formatTime(editor.params.hour, editor.params.minute)"
                                   @input="setTimeFromEvent($event)">
                        </x-admin3.field>
                    </div>

                    <div class="v3-gap v3-grid v3-grid--2" x-show="editor.preset === 'monthly'" x-cloak>
                        <x-admin3.field name="monthly_day" label="Day of month" hint="Capped at 28 to avoid month-end gaps.">
                            <select class="v3-control" x-model.number="editor.params.day_of_month">
                                <template x-for="d in 28" :key="d">
                                    <option :value="d" x-text="d"></option>
                                </template>
                            </select>
                        </x-admin3.field>
                        <x-admin3.field name="monthly_time" label="Time">
                            <input type="time" class="v3-control v3-mono" :value="formatTime(editor.params.hour, editor.params.minute)"
                                   @input="setTimeFromEvent($event)">
                        </x-admin3.field>
                    </div>

                    <div class="v3-well v3-gap">
                        <div class="v3-label">Preview</div>
                        <div x-text="previewText()"></div>
                        <code class="v3-mono v3-cell-quiet" x-text="previewCron()"></code>
                    </div>

                    <p class="v3-field__error" x-show="editor.error" x-cloak x-text="editor.error"></p>

                    <div class="v3-modal__acts">
                        <button type="button" class="v3-btn v3-btn--ghost" @click="closeEditor()">Cancel</button>
                        <button type="button" class="v3-btn v3-btn--primary" @click="saveEditor()" :disabled="editor.saving"
                                x-text="editor.saving ? 'Saving…' : 'Save schedule'">Save schedule</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Run-now confirm. Hand-rolled because Confirm resolves in JS
             (runNow) rather than a form action. --}}
        <template x-if="pendingRun">
            <div class="v3-overlay" @click.self="pendingRun = null" @keydown.escape.window="pendingRun = null">
                <div class="v3-modal" role="dialog" aria-modal="true" aria-labelledby="run-automation-title">
                    <h2 class="v3-modal__title" id="run-automation-title">Run this automation now?</h2>
                    <p class="v3-modal__body">
                        <span class="v3-mono" x-text="pendingRun.command"></span>
                        runs immediately, on top of its schedule. It can take up to five minutes and does everything
                        a scheduled run does, including sending any email it sends.
                    </p>
                    <div class="v3-modal__acts">
                        <button type="button" class="v3-btn v3-btn--ghost" x-ref="cancelRun" @click="pendingRun = null">Cancel</button>
                        <button type="button" class="v3-btn v3-btn--primary" @click="runNow()">Run now</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @push('scripts')
    <script>
        window.automationsTab = function (config) {
            return {
                updateUrlTemplate: config.updateUrlTemplate,
                toggleUrlTemplate: config.toggleUrlTemplate,
                runUrlTemplate: config.runUrlTemplate,

                pendingRun: null,

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

                urlFor(template, id) {
                    return template.replace('__ID__', id);
                },

                toast(text, ok = true) {
                    window.Alpine.store('toasts').push(text, ok ? 'good' : 'bad');
                },

                // Optimistic flip; the row's Alpine scope owns `enabled`.
                async toggle(id, evt) {
                    const scope = Alpine.$data(evt.currentTarget.closest('[x-data]'));
                    const prev = scope.enabled;
                    scope.enabled = !prev;
                    const r = await window.v3Post(this.urlFor(this.toggleUrlTemplate, id));
                    if (!r.ok) scope.enabled = prev;
                },

                // The row's scope (which owns `running`) is captured at click
                // time: by the time the confirm's button is clicked the
                // nearest [x-data] is the dialog's, not the row's.
                askRun(id, command, evt) {
                    this.pendingRun = {
                        id,
                        command,
                        scope: Alpine.$data(evt.currentTarget.closest('[x-data]')),
                    };
                    this.$nextTick(() => this.$refs.cancelRun?.focus());
                },

                async runNow() {
                    if (!this.pendingRun) return;
                    const { id, command, scope } = this.pendingRun;
                    this.pendingRun = null;

                    scope.running = true;
                    this.toast('Running ' + command + '… This can take up to 5 minutes.');
                    try {
                        const res = await fetch(this.urlFor(this.runUrlTemplate, id), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) {
                            const msg = data.message || ('HTTP ' + res.status);
                            this.toast(command + ' failed: ' + msg + (data.output ? ': ' + data.output.slice(0, 150) : ''), false);
                        } else {
                            this.toast(command + ' completed' + (data.message ? ': ' + data.message : '') + (data.output ? ': ' + data.output.slice(0, 150) : ''));
                        }
                    } catch (e) {
                        this.toast('Run failed: ' + (e.message || 'Network error'), false);
                    } finally {
                        scope.running = false;
                    }
                },

                openEditor(automation) {
                    this.editor.id = automation.id;
                    this.editor.name = automation.name;
                    this.editor.command = automation.command;
                    this.editor.preset = automation.schedule_preset;
                    this.editor.error = null;

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
                    if (this.editor.preset === 'every_n_minutes') {
                        const allowed = [1, 5, 10, 15, 20, 30];
                        if (!allowed.includes(this.editor.params.minute)) {
                            this.editor.params.minute = 15;
                        }
                    }
                },

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

                previewText() {
                    const p = this.editor.params;
                    const pad = (n) => String(n).padStart(2, '0');
                    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    switch (this.editor.preset) {
                        case 'every_n_minutes':
                            return p.minute === 1 ? 'Runs every minute.' : 'Runs every ' + p.minute + ' minutes.';
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

                async saveEditor() {
                    this.editor.saving = true;
                    this.editor.error = null;

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
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
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
                        this.toast('Schedule updated: ' + this.editor.name);
                        // Reload so the table reflects the schedule and cron
                        // expression exactly as the server rendered them.
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
    @endpush
</x-admin3.settings-shell>
