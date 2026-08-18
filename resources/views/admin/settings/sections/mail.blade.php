<x-admin.settings-shell title="Notifications" active="mail">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Notifications</h1>
            <p class="v-head__standing">The mail server and sender every order confirmation and alert leaves through.</p>
        </div>
    </div>

    <x-admin.form :action="route('admin.settings.update')" class="v-settle" id="v-mail-form">
        <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'mail') }}">

        <x-admin.panel title="Mail server">
            <x-admin.field name="groups.mail.driver" label="Driver">
                <select name="groups[mail][driver]" class="v-control">
                    @foreach (['smtp' => 'SMTP', 'mail' => 'PHP Mail / Sendmail'] as $v => $label)
                        <option value="{{ $v }}" @selected(($settings['mail']['driver'] ?? 'smtp') === $v)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.field>
            <div class="v-grid v-grid--2 v-gap">
                <x-admin.field name="groups.mail.host" label="Host">
                    <input type="text" name="groups[mail][host]" class="v-control v-mono" autocomplete="off"
                           value="{{ $settings['mail']['host'] ?? '' }}">
                </x-admin.field>
                <x-admin.field name="groups.mail.port" label="Port">
                    <input type="number" name="groups[mail][port]" class="v-control v-mono" autocomplete="off"
                           value="{{ $settings['mail']['port'] ?? '587' }}">
                </x-admin.field>
                <x-admin.field name="groups.mail.username" label="Username">
                    <input type="text" name="groups[mail][username]" class="v-control" autocomplete="off"
                           value="{{ $settings['mail']['username'] ?? '' }}">
                </x-admin.field>
                <x-admin.field name="groups.mail.password" label="Password">
                    <x-admin.secret name="groups[mail][password]"
                        :masked="!empty($settings['mail']['password']) ? str_repeat('•', 12) : null"
                        placeholder="SMTP password" />
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="groups.mail.encryption" label="Encryption">
                    <select name="groups[mail][encryption]" class="v-control">
                        @foreach (['' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'] as $v => $label)
                            <option value="{{ $v }}" @selected(($settings['mail']['encryption'] ?? 'tls') === $v)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Sender">
            <div class="v-grid v-grid--2">
                <x-admin.field name="groups.mail.from_address" label="From address">
                    <input type="email" name="groups[mail][from_address]" class="v-control" autocomplete="off"
                           value="{{ $settings['mail']['from_address'] ?? '' }}">
                </x-admin.field>
                <x-admin.field name="groups.mail.from_name" label="From name">
                    <input type="text" name="groups[mail][from_name]" class="v-control"
                           value="{{ $settings['mail']['from_name'] ?? '' }}">
                </x-admin.field>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Send a test" note="Tests the values as typed above, saved or not.">
            <div class="v-inline" x-data="{ to: @js(auth()->user()->email), sending: false }">
                <x-admin.field name="test_to" label="Send to">
                    {{-- Enter runs the test, never the page save: the panel
                         rides inside the one form, and a test must not submit. --}}
                    <input type="email" class="v-control" x-model="to"
                           @keydown.enter.prevent="$el.closest('.v-inline').querySelector('button').click()">
                </x-admin.field>
                <button type="button" class="v-btn v-btn--secondary" :disabled="sending || !to"
                        @click="
                            sending = true;
                            const f = new FormData(document.getElementById('v-mail-form'));
                            vPost(@js(route('admin.settings.test-mail')), {
                                to,
                                override: {
                                    driver: f.get('groups[mail][driver]'),
                                    host: f.get('groups[mail][host]'),
                                    port: f.get('groups[mail][port]'),
                                    username: f.get('groups[mail][username]'),
                                    password: f.get('groups[mail][password]') || '',
                                    encryption: f.get('groups[mail][encryption]'),
                                    from_address: f.get('groups[mail][from_address]'),
                                    from_name: f.get('groups[mail][from_name]'),
                                },
                            }).finally(() => sending = false);
                        ">
                    <span x-text="sending ? 'Sending…' : 'Send test email'">Send test email</span>
                </button>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.settings-shell>
