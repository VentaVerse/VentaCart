{{--
    Automations Tab — admin/settings

    Design rationale:
    - Matches the existing settings-table aesthetic (rounded border container, gray-50 thead,
      divide-y rows, indigo as primary action, emerald/red/amber pills for status).
    - No custom CSS; all Tailwind utility classes + Alpine.js as required by the codebase.
    - Desktop: table layout for scannability (admins compare schedules across rows).
      Mobile (<md): same data re-rendered as cards so columns don't wrap awkwardly.
    - Modal is a single component instance (one at a time); opened by passing the row data into
      `openEditor(automation)`. Keeps DOM light — no modal-per-row.
    - Toast banner sits fixed top-right and auto-dismisses; kept local (no dependency on a global
      toast store) so this partial is self-contained.
    - "Run Now" is never hidden when disabled — button stays in place (prevents layout jumps),
      just greyed with tooltip. Requirement #2 from the brief.
    - Every-1-minute warning uses amber, not red (it's a caution, not an error).
    - Colors drawn from the existing palette in this file: slate/gray neutrals, indigo primary,
      emerald success, red danger, amber caution. No gradients, no Inter, no AI-generic styling.
--}}

@php
    $tz = config('app.timezone', 'UTC');
    $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $crontabLine = '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';
@endphp

<div
    x-data="automationsTab({
        updateUrlTemplate: '{{ route('admin.automations.update', ['automation' => '__ID__']) }}',
        toggleUrlTemplate: '{{ route('admin.automations.toggle', ['automation' => '__ID__']) }}',
        runUrlTemplate:    '{{ route('admin.automations.runNow', ['automation' => '__ID__']) }}',
        csrf: '{{ csrf_token() }}',
    })"
    class="p-6"
>
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-5">
        <div>
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Automations</h3>
            <p class="text-xs text-gray-500 mt-1">
                Scheduled background tasks. Times are in
                <span class="font-mono text-gray-700">{{ $tz }}</span>.
            </p>
        </div>
    </div>

    {{-- Crontab hint banner --}}
    <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3"
         x-data="{ copied: false }">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs text-gray-600 mb-2">
                    For the scheduler to fire, this line must live in the server's crontab:
                </p>
                <div class="flex items-center gap-2 flex-wrap">
                    <code class="flex-1 min-w-0 block bg-white border border-gray-200 rounded px-3 py-2 text-xs font-mono text-gray-800 overflow-x-auto whitespace-nowrap">{{ $crontabLine }}</code>
                    <button type="button"
                            @click="navigator.clipboard.writeText(@js($crontabLine)).then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                            class="flex-shrink-0 px-3 py-2 text-xs font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                        <span x-show="!copied">Copy</span>
                        <span x-show="copied" x-cloak class="text-emerald-600">Copied</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block overflow-hidden rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Automation</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Command</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Schedule <span class="normal-case text-gray-400 font-normal">({{ $tz }})</span></th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enabled</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Run</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
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
                    <tr class="hover:bg-gray-50 align-top"
                        x-data="{ enabled: {{ $automation->enabled ? 'true' : 'false' }}, running: false }">
                        <td class="px-4 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $automation->name }}</div>
                            <div class="text-xs text-gray-500 mt-0.5 max-w-sm">{{ $automation->description }}</div>
                        </td>
                        <td class="px-4 py-4">
                            <code class="text-xs font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-700 whitespace-nowrap">{{ $automation->command }}</code>
                        </td>
                        <td class="px-4 py-4">
                            <div class="text-sm text-gray-700">{{ $automation->schedule_description }}</div>
                            <code class="text-[11px] font-mono text-gray-400 mt-0.5 block">{{ $automation->cron_expression }}</code>
                        </td>
                        <td class="px-4 py-4">
                            {{-- iOS-style toggle --}}
                            <button type="button"
                                    role="switch"
                                    :aria-checked="enabled.toString()"
                                    @click="toggle({{ $automation->id }}, $event)"
                                    :class="enabled ? 'bg-indigo-600' : 'bg-gray-300'"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1">
                                <span :class="enabled ? 'translate-x-4' : 'translate-x-0.5'"
                                      class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                            </button>
                        </td>
                        <td class="px-4 py-4">
                            @if ($automation->last_run_status === 'success')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Success</span>
                            @elseif ($automation->last_run_status === 'failed')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>
                            @elseif ($automation->last_run_status === 'running')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 animate-pulse">Running</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Never run</span>
                            @endif
                            @if ($automation->last_run_at)
                                <div class="text-[11px] text-gray-400 mt-1">{{ $automation->last_run_at->timezone($tz)->format('M j, Y H:i') }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-2">
                                <button type="button"
                                        @click='openEditor({!! $editPayload !!})'
                                        class="px-2.5 py-1.5 text-xs font-medium rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                                    Edit
                                </button>
                                <span :title="enabled ? '' : 'Enable this automation to run it manually'"
                                      class="inline-block">
                                    <button type="button"
                                            @click="runNow({{ $automation->id }}, @js($automation->command), $event)"
                                            :disabled="!enabled || running"
                                            :class="(!enabled || running) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-700'"
                                            class="px-2.5 py-1.5 text-xs font-medium rounded bg-indigo-600 text-white transition-colors">
                                        <span x-show="!running">Run Now</span>
                                        <span x-show="running" x-cloak>Running...</span>
                                    </button>
                                </span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">No automations configured.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile cards --}}
    <div class="md:hidden space-y-3">
        @forelse ($automations as $automation)
            <div class="rounded-lg border border-gray-200 bg-white p-4"
                 x-data="{ enabled: {{ $automation->enabled ? 'true' : 'false' }}, running: false }">
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-gray-900">{{ $automation->name }}</div>
                        <code class="text-[11px] font-mono text-gray-500 block mt-0.5">{{ $automation->command }}</code>
                    </div>
                    @if ($automation->last_run_status === 'success')
                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Success</span>
                    @elseif ($automation->last_run_status === 'failed')
                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>
                    @elseif ($automation->last_run_status === 'running')
                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 animate-pulse">Running</span>
                    @else
                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Never run</span>
                    @endif
                </div>

                <p class="text-xs text-gray-500 mb-3">{{ $automation->description }}</p>

                <div class="grid grid-cols-2 gap-3 text-xs mb-3 py-2 border-y border-gray-100">
                    <div>
                        <div class="text-gray-400 uppercase tracking-wide text-[10px] mb-0.5">Schedule</div>
                        <div class="text-gray-700">{{ $automation->schedule_description }}</div>
                    </div>
                    <div>
                        <div class="text-gray-400 uppercase tracking-wide text-[10px] mb-0.5">Last Run</div>
                        <div class="text-gray-700">
                            {{ $automation->last_run_at ? $automation->last_run_at->timezone($tz)->format('M j, H:i') : '—' }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <button type="button"
                                role="switch"
                                :aria-checked="enabled.toString()"
                                @click="toggle({{ $automation->id }}, $event)"
                                :class="enabled ? 'bg-indigo-600' : 'bg-gray-300'"
                                class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors">
                            <span :class="enabled ? 'translate-x-4' : 'translate-x-0.5'"
                                  class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                        </button>
                        <span x-text="enabled ? 'Enabled' : 'Disabled'"></span>
                    </label>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click='openEditor({!! $editPayload !!})'
                                class="px-3 py-1.5 text-xs font-medium rounded border border-gray-300 bg-white text-gray-700">
                            Edit
                        </button>
                        <span :title="enabled ? '' : 'Enable this automation to run it manually'">
                            <button type="button"
                                    @click="runNow({{ $automation->id }}, @js($automation->command), $event)"
                                    :disabled="!enabled || running"
                                    :class="(!enabled || running) ? 'opacity-40 cursor-not-allowed' : ''"
                                    class="px-3 py-1.5 text-xs font-medium rounded bg-indigo-600 text-white">
                                <span x-show="!running">Run Now</span>
                                <span x-show="running" x-cloak>...</span>
                            </button>
                        </span>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-center text-sm text-gray-400">
                No automations configured.
            </div>
        @endforelse
    </div>

    {{-- ============================ EDIT MODAL ============================ --}}
    <div x-show="editor.open"
         x-cloak
         @keydown.escape.window="closeEditor()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition.opacity>
        <div class="absolute inset-0 bg-gray-900/50" @click="closeEditor()"></div>

        <div class="relative bg-white rounded-lg shadow-xl border border-gray-200 w-full max-w-lg max-h-[90vh] overflow-y-auto"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0">

            <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900" x-text="editor.name"></h4>
                    <code class="text-xs font-mono text-gray-500" x-text="editor.command"></code>
                </div>
                <button type="button" @click="closeEditor()" class="text-gray-400 hover:text-gray-600 p-1 -m-1">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-4">
                {{-- Frequency --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wide mb-1">Frequency</label>
                    <select x-model="editor.preset"
                            @change="onPresetChange()"
                            class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="every_n_minutes">Every N Minutes</option>
                        <option value="hourly">Hourly</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>

                {{-- Every N Minutes --}}
                <div x-show="editor.preset === 'every_n_minutes'">
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wide mb-1">Interval</label>
                    <select x-model.number="editor.params.minute"
                            class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <template x-for="n in [1, 5, 10, 15, 20, 30]" :key="n">
                            <option :value="n" x-text="'Every ' + n + ' minute' + (n === 1 ? '' : 's')"></option>
                        </template>
                    </select>
                    <div x-show="editor.preset === 'every_n_minutes' && editor.params.minute === 1"
                         x-cloak
                         class="mt-2 flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2">
                        <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z"/>
                        </svg>
                        <p class="text-xs text-amber-800">
                            <span class="font-medium">Fires every minute.</span>
                            This puts steady load on your server — use sparingly, only for time-sensitive tasks.
                        </p>
                    </div>
                </div>

                {{-- Hourly --}}
                <div x-show="editor.preset === 'hourly'" x-cloak>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wide mb-1">Minute of the hour (0–59)</label>
                    <input type="number" min="0" max="59" step="1"
                           x-model.number="editor.params.minute"
                           class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Daily --}}
                <div x-show="editor.preset === 'daily'" x-cloak>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wide mb-1">Time of day</label>
                    <input type="time" :value="formatTime(editor.params.hour, editor.params.minute)"
                           @input="setTimeFromEvent($event)"
                           class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                {{-- Weekly --}}
                <div x-show="editor.preset === 'weekly'" x-cloak class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wide mb-1">Day</label>
                        <select x-model.number="editor.params.day_of_week"
                                class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($daysOfWeek as $i => $day)
                                <option value="{{ $i }}">{{ $day }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wide mb-1">Time</label>
                        <input type="time" :value="formatTime(editor.params.hour, editor.params.minute)"
                               @input="setTimeFromEvent($event)"
                               class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- Monthly --}}
                <div x-show="editor.preset === 'monthly'" x-cloak class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wide mb-1">Day of month</label>
                        <select x-model.number="editor.params.day_of_month"
                                class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <template x-for="d in 28" :key="d">
                                <option :value="d" x-text="d"></option>
                            </template>
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Capped at 28 to avoid month-end gaps.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wide mb-1">Time</label>
                        <input type="time" :value="formatTime(editor.params.hour, editor.params.minute)"
                               @input="setTimeFromEvent($event)"
                               class="block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="rounded-md border border-indigo-100 bg-indigo-50/60 px-3 py-3">
                    <div class="text-[10px] font-semibold text-indigo-700 uppercase tracking-wider mb-1">Preview</div>
                    <div class="text-sm text-gray-800" x-text="previewText()"></div>
                    <code class="block mt-1 text-xs font-mono text-gray-600" x-text="previewCron()"></code>
                    <div class="text-[11px] text-gray-500 mt-1">Runs in <span class="font-mono">{{ $tz }}</span></div>
                </div>

                {{-- Error --}}
                <div x-show="editor.error" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="editor.error"></div>
            </div>

            <div class="px-5 py-3 border-t border-gray-200 flex items-center justify-end gap-2 bg-gray-50">
                <button type="button" @click="closeEditor()"
                        class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" @click="saveEditor()"
                        :disabled="editor.saving"
                        :class="editor.saving ? 'opacity-60 cursor-not-allowed' : 'hover:bg-indigo-700'"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white">
                    <span x-show="!editor.saving">Save Schedule</span>
                    <span x-show="editor.saving" x-cloak>Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ============================ TOAST ============================ --}}
    <div x-show="toast.show"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed top-4 right-4 z-[60] w-80 max-w-[calc(100vw-2rem)] rounded-lg shadow-lg border"
         :class="{
             'bg-emerald-50 border-emerald-200': toast.type === 'success',
             'bg-red-50 border-red-200': toast.type === 'error',
             'bg-gray-50 border-gray-200': toast.type === 'info',
         }">
        <div class="p-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 mt-0.5">
                    <template x-if="toast.type === 'success'">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </template>
                    <template x-if="toast.type === 'error'">
                        <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </template>
                    <template x-if="toast.type === 'info'">
                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </template>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900" x-text="toast.title"></p>
                    <p x-show="toast.message" class="text-xs text-gray-600 mt-1" x-text="toast.message"></p>
                    <pre x-show="toast.output"
                         x-cloak
                         class="mt-2 max-h-32 overflow-y-auto text-[11px] font-mono bg-white border border-gray-200 rounded p-2 text-gray-700 whitespace-pre-wrap"
                         x-text="toast.output"></pre>
                </div>
                <button type="button" @click="toast.show = false" class="flex-shrink-0 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
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

            toast: {
                show: false,
                type: 'info',
                title: '',
                message: '',
                output: '',
                timer: null,
            },

            // ---------- URL helpers ----------
            urlFor(template, id) {
                return template.replace('__ID__', id);
            },

            // ---------- Toast ----------
            showToast(type, title, message = '', output = '') {
                if (this.toast.timer) clearTimeout(this.toast.timer);
                this.toast.type = type;
                this.toast.title = title;
                this.toast.message = message;
                this.toast.output = output;
                this.toast.show = true;
                this.toast.timer = setTimeout(() => { this.toast.show = false; }, 5000);
            },

            // ---------- Toggle enabled ----------
            async toggle(id, evt) {
                const row = evt.currentTarget.closest('[x-data]')?._x_dataStack?.[0];
                // Optimistic flip via Alpine reactive: find the scope holding `enabled`.
                // We walk up: the button's Alpine scope exposes `enabled` directly.
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
