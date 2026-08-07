<x-admin.settings-shell title="Mail">
    <div class="space-y-6">
        <x-admin.card title="SMTP" description="Outgoing mail server used for order and customer emails.">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="mail">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'mail') }}">
                <x-admin.field name="settings[driver]" label="Driver">
                    <x-admin.select name="settings[driver]">
                        @foreach (['smtp' => 'SMTP', 'mail' => 'PHP Mail / Sendmail'] as $v => $label)
                            <option value="{{ $v }}" @selected(($settings['mail']['driver'] ?? 'smtp') === $v)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-admin.field name="settings[host]" label="Host">
                        <x-admin.input name="settings[host]" :value="$settings['mail']['host'] ?? ''" />
                    </x-admin.field>
                    <x-admin.field name="settings[port]" label="Port">
                        <x-admin.input name="settings[port]" type="number" :value="$settings['mail']['port'] ?? '587'" />
                    </x-admin.field>
                    <x-admin.field name="settings[username]" label="Username">
                        <x-admin.input name="settings[username]" :value="$settings['mail']['username'] ?? ''" />
                    </x-admin.field>
                    <x-admin.field name="settings[password]" label="Password">
                        <x-admin.input name="settings[password]" type="password" :value="$settings['mail']['password'] ?? ''" />
                    </x-admin.field>
                </div>
                <x-admin.field name="settings[encryption]" label="Encryption">
                    <x-admin.select name="settings[encryption]">
                        @foreach (['' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'] as $v => $label)
                            <option value="{{ $v }}" @selected(($settings['mail']['encryption'] ?? 'tls') === $v)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save SMTP settings</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>

        <x-admin.card title="Sender" description="The From identity on outgoing email.">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
                @csrf
                <input type="hidden" name="group" value="mail">
                <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'mail') }}">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-admin.field name="settings[from_address]" label="From address">
                        <x-admin.input name="settings[from_address]" type="email" :value="$settings['mail']['from_address'] ?? ''" />
                    </x-admin.field>
                    <x-admin.field name="settings[from_name]" label="From name">
                        <x-admin.input name="settings[from_name]" :value="$settings['mail']['from_name'] ?? ''" />
                    </x-admin.field>
                </div>
                <x-admin.form-actions>
                    <x-admin.button variant="primary" type="submit">Save sender</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>

        <x-admin.card title="Send a test email" description="Verifies the configuration above by sending a real email.">
            <form method="POST" action="{{ route('admin.settings.test-mail') }}" data-v-form class="flex items-end gap-3">
                @csrf
                <div class="flex-1">
                    <x-admin.field name="to" label="Send to">
                        <x-admin.input name="to" type="email" placeholder="you@example.com" />
                    </x-admin.field>
                </div>
                <x-admin.button variant="secondary" type="submit">Send test email</x-admin.button>
            </form>
        </x-admin.card>
    </div>
</x-admin.settings-shell>
