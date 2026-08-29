@props(['key', 'title', 'note' => null])
@php
    // One automation's switch and schedule, on the page of the feature that
    // owns it. The row is the same one Settings, Automations lists and the
    // schedule:run heartbeat reads; this is a second door, never a copy.
    // The owning extension seeds the row; a missing row renders nothing
    // rather than inventing one here.
    $automation = \App\Models\Automation::where('key', $key)->first();
    $heartbeatMissing = $automation ? \App\Models\Automation::heartbeatMissing() : false;
    $crontabLine = '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';
    $tz = config('app.timezone');
    $field = fn (string $name) => 'automation[' . $key . '][' . $name . ']';
    $param = fn (string $name) => 'automation[' . $key . '][schedule_params][' . $name . ']';
@endphp
@if ($automation)
    <x-admin.panel :title="$title" :note="$note"
        x-data="vAutomation({{ \Illuminate\Support\Js::from([
            'preset' => $automation->schedule_preset,
            'params' => $automation->schedule_params ?: (object) [],
            'runUrl' => route('admin.automations.runNow', $automation),
        ]) }})">
        <x-slot:acts>
            <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="run()" :disabled="running"
                    title="Runs the job as it is saved, not as it is being edited">
                <span x-show="!running">Run now</span>
                <span x-show="running" x-cloak>Running</span>
            </button>
        </x-slot:acts>

        {{-- Heartbeat: a STATE, not an instruction. Without it an operator
             switches a job on, watches nothing happen, and blames the job. --}}
        @if ($heartbeatMissing)
            <div class="v-well v-gap" role="status">
                <span class="v-badge v-badge--warn">The schedule:run cron is not running on this server</span>
                <input type="text" readonly value="{{ $crontabLine }}" onclick="this.select()"
                       class="v-control v-mono v-gap-sm" aria-label="Crontab line">
            </div>
        @endif

        <label class="v-toggle">
            <input type="hidden" name="{{ $field('enabled') }}" value="0">
            <input type="checkbox" name="{{ $field('enabled') }}" value="1" @checked($automation->enabled)>
            <span class="v-toggle__track" aria-hidden="true"></span>
            {{ $automation->name }}
        </label>

        <div class="v-gap v-grid v-grid--2">
            <x-admin.field :name="$field('schedule_preset')" label="Frequency">
                <select name="{{ $field('schedule_preset') }}" class="v-control" x-model="preset" @change="onPresetChange()">
                    <option value="every_n_minutes">Every N minutes</option>
                    <option value="hourly">Hourly</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </x-admin.field>

            {{-- One block per frequency. Inputs outside the chosen block are
                 disabled, and a disabled input does not submit, so the row
                 stores only the parameters its frequency uses. --}}
            <div x-show="preset === 'every_n_minutes'">
                <x-admin.field name="interval" label="Interval">
                    <select name="{{ $param('minute') }}" class="v-control" x-model.number="params.minute" :disabled="preset !== 'every_n_minutes'">
                        @foreach (\App\Services\CronExpressionBuilder::ALLOWED_MINUTE_INTERVALS as $n)
                            <option value="{{ $n }}">Every {{ $n }} {{ $n === 1 ? 'minute' : 'minutes' }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
            </div>
            <div x-show="preset === 'hourly'" x-cloak>
                <x-admin.field name="hourly_minute" label="Minute of the hour">
                    <input type="number" min="0" max="59" step="1" name="{{ $param('minute') }}" class="v-control v-mono"
                           x-model.number="params.minute" :disabled="preset !== 'hourly'">
                </x-admin.field>
            </div>
            <div x-show="preset === 'daily'" x-cloak>
                <x-admin.field name="daily_time" label="Time of day">
                    <input type="time" class="v-control v-mono" :value="time()" @input="setTime($event)">
                    <input type="hidden" name="{{ $param('hour') }}" :value="params.hour" :disabled="preset !== 'daily'">
                    <input type="hidden" name="{{ $param('minute') }}" :value="params.minute" :disabled="preset !== 'daily'">
                </x-admin.field>
            </div>
            <div x-show="preset === 'weekly'" x-cloak class="v-grid v-grid--2">
                <x-admin.field name="weekly_day" label="Day">
                    <select name="{{ $param('day_of_week') }}" class="v-control" x-model.number="params.day_of_week" :disabled="preset !== 'weekly'">
                        @foreach (\App\Services\CronExpressionBuilder::DAYS_OF_WEEK as $i => $day)
                            <option value="{{ $i }}">{{ $day }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field name="weekly_time" label="Time">
                    <input type="time" class="v-control v-mono" :value="time()" @input="setTime($event)">
                    <input type="hidden" name="{{ $param('hour') }}" :value="params.hour" :disabled="preset !== 'weekly'">
                    <input type="hidden" name="{{ $param('minute') }}" :value="params.minute" :disabled="preset !== 'weekly'">
                </x-admin.field>
            </div>
            <div x-show="preset === 'monthly'" x-cloak class="v-grid v-grid--2">
                <x-admin.field name="monthly_day" label="Day of month" hint="Capped at 28 so no month is skipped.">
                    <select name="{{ $param('day_of_month') }}" class="v-control" x-model.number="params.day_of_month" :disabled="preset !== 'monthly'">
                        @for ($d = 1; $d <= 28; $d++)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endfor
                    </select>
                </x-admin.field>
                <x-admin.field name="monthly_time" label="Time">
                    <input type="time" class="v-control v-mono" :value="time()" @input="setTime($event)">
                    <input type="hidden" name="{{ $param('hour') }}" :value="params.hour" :disabled="preset !== 'monthly'">
                    <input type="hidden" name="{{ $param('minute') }}" :value="params.minute" :disabled="preset !== 'monthly'">
                </x-admin.field>
            </div>
        </div>

        <p class="v-subnote v-gap-sm">
            <span x-text="preview()"></span>
            <code class="v-mono v-cell-quiet" x-text="cron()"></code>
        </p>

        <p class="v-subnote v-cell-quiet">
            @if ($automation->last_run_at)
                Last run {{ $automation->last_run_at->timezone($tz)->format('M j, Y H:i') }}
                @if ($automation->last_run_status === 'failed')
                    <span class="v-badge v-badge--bad">failed</span>
                @elseif ($automation->enabled && $automation->isUnhealthy())
                    <span class="v-badge v-badge--warn">overdue</span>
                @else
                    <span class="v-badge v-badge--good">{{ $automation->last_run_status ?: 'ran' }}</span>
                @endif
            @else
                Never run
            @endif
            <span x-show="ran" x-cloak x-text="ran"></span>
        </p>
        @error('automation.' . $key . '.schedule_params.minute')
            <p class="v-field__error">{{ $message }}</p>
        @enderror
    </x-admin.panel>
@endif
