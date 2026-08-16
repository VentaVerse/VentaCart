<x-admin3.settings-shell title="Notifications" active="mail">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Notifications</h1>
            <p class="v3-head__standing">The mail server and sender every order confirmation and alert leaves through.</p>
        </div>
    </div>

    <x-admin3.form :action="route('admin.settings.update')" class="v3-settle" id="v3-mail-form">
        <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'mail') }}">

        <x-admin3.panel title="Mail server">
            <x-admin3.field name="groups.mail.driver" label="Driver">
                <select name="groups[mail][driver]" class="v3-control">
                    @foreach (['smtp' => 'SMTP', 'mail' => 'PHP Mail / Sendmail'] as $v => $label)
                        <option value="{{ $v }}" @selected(($settings['mail']['driver'] ?? 'smtp') === $v)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin3.field>
            <div class="v3-grid v3-grid--2 v3-gap">
                <x-admin3.field name="groups.mail.host" label="Host">
                    <input type="text" name="groups[mail][host]" class="v3-control v3-mono" autocomplete="off"
                           value="{{ $settings['mail']['host'] ?? '' }}">
                </x-admin3.field>
                <x-admin3.field name="groups.mail.port" label="Port">
                    <input type="number" name="groups[mail][port]" class="v3-control v3-mono" autocomplete="off"
                           value="{{ $settings['mail']['port'] ?? '587' }}">
                </x-admin3.field>
                <x-admin3.field name="groups.mail.username" label="Username">
                    <input type="text" name="groups[mail][username]" class="v3-control" autocomplete="off"
                           value="{{ $settings['mail']['username'] ?? '' }}">
                </x-admin3.field>
                <x-admin3.field name="groups.mail.password" label="Password">
                    <x-admin3.secret name="groups[mail][password]"
                        :masked="!empty($settings['mail']['password']) ? str_repeat('•', 12) : null"
                        placeholder="SMTP password" />
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="groups.mail.encryption" label="Encryption">
                    <select name="groups[mail][encryption]" class="v3-control">
                        @foreach (['' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'] as $v => $label)
                            <option value="{{ $v }}" @selected(($settings['mail']['encryption'] ?? 'tls') === $v)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-admin3.field>
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="Sender">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="groups.mail.from_address" label="From address">
                    <input type="email" name="groups[mail][from_address]" class="v3-control" autocomplete="off"
                           value="{{ $settings['mail']['from_address'] ?? '' }}">
                </x-admin3.field>
                <x-admin3.field name="groups.mail.from_name" label="From name">
                    <input type="text" name="groups[mail][from_name]" class="v3-control"
                           value="{{ $settings['mail']['from_name'] ?? '' }}">
                </x-admin3.field>
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="Send a test" note="Tests the values as typed above, saved or not.">
            <div class="v3-inline" x-data="{ to: '{{ auth()->user()->email }}', sending: false }">
                <x-admin3.field name="test_to" label="Send to">
                    <input type="email" class="v3-control" x-model="to">
                </x-admin3.field>
                <button type="button" class="v3-btn v3-btn--secondary" :disabled="sending || !to"
                        @click="
                            sending = true;
                            const f = new FormData(document.getElementById('v3-mail-form'));
                            v3Post(@js(route('admin.settings.test-mail')), {
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
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.settings-shell>
