@php
    $mode = $settings['payment']['hitpay_mode'] ?? 'sandbox';
@endphp
<div class="space-y-6">
    <x-admin.card title="HitPay" description="Online payment gateway (credit card, e-wallets).">
        <x-slot name="headerActions">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form>
                @csrf
                <input type="hidden" name="group" value="payment">
                <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'hitpay') }}">
                <label class="flex items-center gap-2 text-[13px] text-v-secondary cursor-pointer">
                    <span>Enabled</span>
                    <x-admin.toggle name="settings[hitpay_enabled]"
                        :checked="($settings['payment']['hitpay_enabled'] ?? '0') === '1'"
                        x-on:change="$el.closest('form').submit()" />
                </label>
            </form>
        </x-slot>

        <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5" x-data="{ mode: '{{ $mode }}' }">
            @csrf
            <input type="hidden" name="group" value="payment">
            <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'hitpay') }}">

            <x-admin.field name="settings[hitpay_display_name]" label="Checkout label" help="Shown at checkout. Leave blank for default.">
                <x-admin.input name="settings[hitpay_display_name]"
                    :value="$settings['payment']['hitpay_display_name'] ?? ''" placeholder="{{ $defaultName }}" />
            </x-admin.field>

            <x-admin.field name="settings[hitpay_mode]" label="Mode">
                <x-admin.select name="settings[hitpay_mode]" x-model="mode" class="max-w-xs">
                    <option value="sandbox" @selected($mode === 'sandbox')>Sandbox</option>
                    <option value="production" @selected($mode === 'production')>Production</option>
                </x-admin.select>
            </x-admin.field>

            <div x-show="mode === 'sandbox'" x-cloak class="space-y-5 border-l-2 border-v-warning pl-4">
                <p class="text-xs font-medium uppercase tracking-wide text-v-warning">Sandbox credentials</p>
                <x-admin.field name="settings[hitpay_sandbox_api_key]" label="API key"
                    :help="!empty($maskedKeys['hitpay_sandbox_api_key'] ?? null) ? 'Current key ends in ' . $maskedKeys['hitpay_sandbox_api_key'] . ' — leave blank to keep it.' : null">
                    <x-admin.input name="settings[hitpay_sandbox_api_key]" type="password" placeholder="Enter sandbox API key" autocomplete="off" />
                </x-admin.field>
                <x-admin.field name="settings[hitpay_sandbox_salt]" label="Salt"
                    :help="!empty($maskedKeys['hitpay_sandbox_salt'] ?? null) ? 'Current salt ends in ' . $maskedKeys['hitpay_sandbox_salt'] . ' — leave blank to keep it.' : null">
                    <x-admin.input name="settings[hitpay_sandbox_salt]" type="password" placeholder="Enter sandbox salt" autocomplete="off" />
                </x-admin.field>
            </div>

            <div x-show="mode === 'production'" x-cloak class="space-y-5 border-l-2 border-v-accent pl-4">
                <p class="text-xs font-medium uppercase tracking-wide text-v-accent">Production credentials</p>
                <x-admin.field name="settings[hitpay_production_api_key]" label="API key"
                    :help="!empty($maskedKeys['hitpay_production_api_key'] ?? null) ? 'Current key ends in ' . $maskedKeys['hitpay_production_api_key'] . ' — leave blank to keep it.' : null">
                    <x-admin.input name="settings[hitpay_production_api_key]" type="password" placeholder="Enter production API key" autocomplete="off" />
                </x-admin.field>
                <x-admin.field name="settings[hitpay_production_salt]" label="Salt"
                    :help="!empty($maskedKeys['hitpay_production_salt'] ?? null) ? 'Current salt ends in ' . $maskedKeys['hitpay_production_salt'] . ' — leave blank to keep it.' : null">
                    <x-admin.input name="settings[hitpay_production_salt]" type="password" placeholder="Enter production salt" autocomplete="off" />
                </x-admin.field>
            </div>

            <x-admin.field name="settings[hitpay_success_status_id]" label="Order status on success">
                <x-admin.select name="settings[hitpay_success_status_id]" class="max-w-xs">
                    @foreach ($orderStatuses as $status)
                        <option value="{{ $status->id }}" @selected(($settings['payment']['hitpay_success_status_id'] ?? '') == $status->id)>{{ $status->name }}</option>
                    @endforeach
                </x-admin.select>
            </x-admin.field>

            <x-admin.form-actions>
                <x-admin.button variant="primary" type="submit">Save</x-admin.button>
            </x-admin.form-actions>
        </form>
    </x-admin.card>
</div>
