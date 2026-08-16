@php
    $mode = $settings['payment']['hitpay_mode'] ?? 'sandbox';
    // A masked value is asterisks plus a last-4 tail; the tail alone reads
    // as identity. Decryption failures mask fully and get neutral wording.
    $tailHint = function (?string $masked, string $noun) {
        if (empty($masked)) return null;
        $tail = ltrim($masked, '*');
        return $tail !== '' ? "The current {$noun} ends in {$tail}. Blank keeps it." : "A {$noun} is saved. Blank keeps it.";
    };
@endphp
<x-admin3.form :action="route('admin.settings.update')">
    <input type="hidden" name="group" value="payment">
    <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'hitpay') }}">

    <x-admin3.panel title="HitPay">
        <x-slot:acts>
            <input type="hidden" form="hitpay-enable-form" name="group" value="payment">
            <input type="hidden" form="hitpay-enable-form" name="_redirect" value="{{ route('admin.payment-settings.show', 'hitpay') }}">
            <input type="hidden" form="hitpay-enable-form" name="settings[hitpay_enabled]" value="{{ ($settings['payment']['hitpay_enabled'] ?? '0') === '1' ? 0 : 1 }}">
            <button type="submit" form="hitpay-enable-form" class="v3-toggle v3-toggle--btn" role="switch"
                    aria-checked="{{ ($settings['payment']['hitpay_enabled'] ?? '0') === '1' ? 'true' : 'false' }}"
                    aria-label="HitPay enabled">
                <span class="v3-toggle__track" aria-hidden="true"></span>
            </button>
        </x-slot:acts>

        <div class="v3-narrow" x-data="{ mode: @js($mode) }">
            <x-admin3.field name="settings.hitpay_display_name" label="Checkout label" hint="Shown at checkout. Blank uses the default.">
                <input type="text" name="settings[hitpay_display_name]" class="v3-control" placeholder="{{ $defaultName }}"
                       value="{{ $settings['payment']['hitpay_display_name'] ?? '' }}">
            </x-admin3.field>
            <div class="v3-gap">
                <x-admin3.field name="settings.hitpay_mode" label="Mode">
                    <select name="settings[hitpay_mode]" x-model="mode" class="v3-control">
                        <option value="sandbox" @selected($mode === 'sandbox')>Sandbox</option>
                        <option value="production" @selected($mode === 'production')>Production</option>
                    </select>
                </x-admin3.field>
            </div>

            <div class="v3-well v3-gap" x-show="mode === 'sandbox'">
                <div class="v3-label v3-gap-sm">Sandbox credentials</div>
                <x-admin3.field name="settings.hitpay_sandbox_api_key" label="API key"
                    :hint="$tailHint($maskedKeys['hitpay_sandbox_api_key'] ?? null, 'key')">
                    <input type="password" name="settings[hitpay_sandbox_api_key]" autocomplete="off" class="v3-control v3-mono" placeholder="Enter sandbox API key">
                </x-admin3.field>
                <div class="v3-gap">
                    <x-admin3.field name="settings.hitpay_sandbox_salt" label="Salt"
                        :hint="$tailHint($maskedKeys['hitpay_sandbox_salt'] ?? null, 'salt')">
                        <input type="password" name="settings[hitpay_sandbox_salt]" autocomplete="off" class="v3-control v3-mono" placeholder="Enter sandbox salt">
                    </x-admin3.field>
                </div>
            </div>

            <div class="v3-well v3-gap" x-show="mode === 'production'" x-cloak>
                <div class="v3-label v3-gap-sm">Production credentials</div>
                <x-admin3.field name="settings.hitpay_production_api_key" label="API key"
                    :hint="$tailHint($maskedKeys['hitpay_production_api_key'] ?? null, 'key')">
                    <input type="password" name="settings[hitpay_production_api_key]" autocomplete="off" class="v3-control v3-mono" placeholder="Enter production API key">
                </x-admin3.field>
                <div class="v3-gap">
                    <x-admin3.field name="settings.hitpay_production_salt" label="Salt"
                        :hint="$tailHint($maskedKeys['hitpay_production_salt'] ?? null, 'salt')">
                        <input type="password" name="settings[hitpay_production_salt]" autocomplete="off" class="v3-control v3-mono" placeholder="Enter production salt">
                    </x-admin3.field>
                </div>
            </div>

            <div class="v3-gap">
                <x-admin3.field name="settings.hitpay_success_status_id" label="Order status on success">
                    <select name="settings[hitpay_success_status_id]" class="v3-control">
                        @foreach ($orderStatuses as $status)
                            <option value="{{ $status->id }}" @selected(($settings['payment']['hitpay_success_status_id'] ?? '') == $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </x-admin3.field>
            </div>
        </div>
    </x-admin3.panel>
</x-admin3.form>

<form id="hitpay-enable-form" method="POST" action="{{ route('admin.settings.update') }}" hidden>
    @csrf
</form>
