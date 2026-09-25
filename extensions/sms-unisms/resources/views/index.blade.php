<x-admin.shell title="UniSMS">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>UniSMS</span>
            </div>
            <h1 class="v-head__title">UniSMS</h1>
            <p class="v-head__standing">The gateway text messages are sent through.</p>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="sms-unisms" />
        </div>
    </div>

    <div class="v-settle">
        <x-admin.form :action="route('admin.extensions.unisms.update')">
            <x-admin.panel title="Credentials">
                <x-slot:acts>
                    @if ($readiness['ready'])
                        <span class="v-pill v-pill--ok">Ready to send</span>
                    @else
                        <span class="v-pill">{{ $readiness['reason'] }}</span>
                    @endif
                </x-slot:acts>

                <x-admin.field name="api_key" label="API key">
                    <input type="password" name="api_key" autocomplete="off" maxlength="500"
                           class="v-control v-mono"
                           placeholder="{{ $masked ?? 'Paste the key from unismsapi.com' }}">
                    @if ($masked)
                        <x-slot:help>A key is saved, ending {{ substr($masked, -4) }}. Leave blank to keep it.</x-slot:help>
                    @endif
                </x-admin.field>

                <x-admin.field name="sender_id" label="Sender ID" required>
                    <input type="text" name="sender_id" maxlength="32" class="v-control v-w-name"
                           value="{{ old('sender_id', $senderId) }}" autocomplete="off">
                    <x-slot:help>Registered with UniSMS. Philippine telcos refuse a message without one; new accounts get a test ID until a custom one is approved.</x-slot:help>
                </x-admin.field>

                <x-admin.field name="base_url" label="API base URL">
                    <input type="url" name="base_url" maxlength="255" class="v-control v-mono"
                           value="{{ old('base_url', $baseUrl) }}">
                </x-admin.field>

                <div class="v-gap">
                    <label class="v-toggle">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" @checked($enabled)>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        <span>Send messages through UniSMS</span>
                    </label>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Statuses worth texting about" note="A text costs money to send, so nothing texts until it is ticked here.">
                @error('status_ids')
                    <p class="v-field__error">{{ $message }}</p>
                @enderror
                @error('status_ids.*')
                    <p class="v-field__error">{{ $message }}</p>
                @enderror

                <div class="v-gap">
                    @forelse ($statuses as $status)
                        <label class="v-check">
                            <input type="checkbox" name="status_ids[]" value="{{ $status->id }}"
                                   @checked(in_array($status->id, $texted, true))>
                            {{ $status->name }}
                        </label>
                    @empty
                        <p class="v-field__hint">No order statuses yet.</p>
                    @endforelse
                </div>
            </x-admin.panel>
        </x-admin.form>

        {{-- An action, not a record edit, so it posts through vPost like every
             other one-shot button in the admin. Wrapping it in a v form would
             give it a Save, which is not what this button does. --}}
        <x-admin.panel title="Check the connection">
            <x-slot:acts>
                <div x-data="{ busy: false }">
                    <button type="button" class="v-btn v-btn--secondary v-btn--sm"
                            @disabled(! $masked)
                            :disabled="busy"
                            @click="busy = true; vPost(@js(route('admin.extensions.unisms.test'))).finally(() => busy = false)">
                        <span x-text="busy ? 'Checking…' : 'Test connection'">Test connection</span>
                    </button>
                </div>
            </x-slot:acts>
            <p class="v-gap-sm">Asks UniSMS for this account. Costs nothing and sends no message.</p>
        </x-admin.panel>

        <x-admin.panel title="Send a test message">
            <div x-data="{ busy: false, recipient: '', content: @js('Test from ' . config('app.name') . '.') }">
                <x-admin.field name="recipient" label="To">
                    <input type="tel" x-model="recipient" maxlength="20" class="v-control v-mono v-w-name"
                           placeholder="+639171234567">
                    <x-slot:help>E.164, with the plus and country code. UniSMS refuses a local 09 number.</x-slot:help>
                </x-admin.field>

                <x-admin.field name="content" label="Message">
                    <textarea x-model="content" rows="3" maxlength="{{ \Extensions\SmsUnisms\UniSms::MAX_LENGTH }}"
                              class="v-control"></textarea>
                </x-admin.field>

                <div class="v-gap v-actrow">
                    <button type="button" class="v-btn v-btn--secondary"
                            @disabled(! $readiness['ready'])
                            :disabled="busy || ! recipient.trim() || ! content.trim()"
                            @click="busy = true; vPost(@js(route('admin.extensions.unisms.send-test')), { recipient, content }).finally(() => busy = false)">
                        <span x-text="busy ? 'Sending…' : 'Send'">Send</span>
                    </button>
                </div>
            </div>
        </x-admin.panel>
    </div>
</x-admin.shell>
