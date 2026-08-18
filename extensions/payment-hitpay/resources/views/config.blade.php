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
<x-admin.form :action="route('admin.settings.update')" id="pay-hitpay-form">
    <input type="hidden" name="group" value="payment">
    <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'hitpay') }}">

    <x-admin.panel title="HitPay">

        <div class="v-narrow" x-data="{ mode: @js($mode) }">
            <x-admin.field name="settings.hitpay_display_name" label="Checkout label" hint="Shown at checkout. Blank uses the default.">
                <input type="text" name="settings[hitpay_display_name]" class="v-control" placeholder="{{ $defaultName }}"
                       value="{{ $settings['payment']['hitpay_display_name'] ?? '' }}">
            </x-admin.field>
            <div class="v-gap">
                <x-admin.field name="settings.hitpay_mode" label="Mode">
                    <select name="settings[hitpay_mode]" x-model="mode" class="v-control">
                        <option value="sandbox" @selected($mode === 'sandbox')>Sandbox</option>
                        <option value="production" @selected($mode === 'production')>Production</option>
                    </select>
                </x-admin.field>
            </div>

            <div class="v-well v-gap" x-show="mode === 'sandbox'">
                <div class="v-label v-gap-sm">Sandbox credentials</div>
                <x-admin.field name="settings.hitpay_sandbox_api_key" label="API key"
                    :hint="$tailHint($maskedKeys['hitpay_sandbox_api_key'] ?? null, 'key')">
                    <input type="password" name="settings[hitpay_sandbox_api_key]" autocomplete="off" class="v-control v-mono">
                </x-admin.field>
                <div class="v-gap">
                    <x-admin.field name="settings.hitpay_sandbox_salt" label="Salt"
                        :hint="$tailHint($maskedKeys['hitpay_sandbox_salt'] ?? null, 'salt')">
                        <input type="password" name="settings[hitpay_sandbox_salt]" autocomplete="off" class="v-control v-mono">
                    </x-admin.field>
                </div>
            </div>

            <div class="v-well v-gap" x-show="mode === 'production'" x-cloak>
                <div class="v-label v-gap-sm">Production credentials</div>
                <x-admin.field name="settings.hitpay_production_api_key" label="API key"
                    :hint="$tailHint($maskedKeys['hitpay_production_api_key'] ?? null, 'key')">
                    <input type="password" name="settings[hitpay_production_api_key]" autocomplete="off" class="v-control v-mono">
                </x-admin.field>
                <div class="v-gap">
                    <x-admin.field name="settings.hitpay_production_salt" label="Salt"
                        :hint="$tailHint($maskedKeys['hitpay_production_salt'] ?? null, 'salt')">
                        <input type="password" name="settings[hitpay_production_salt]" autocomplete="off" class="v-control v-mono">
                    </x-admin.field>
                </div>
            </div>

            <div class="v-gap">
                <x-admin.field name="settings.hitpay_success_status_id" label="Order status on success">
                    <select name="settings[hitpay_success_status_id]" class="v-control">
                        @foreach ($orderStatuses as $status)
                            <option value="{{ $status->id }}" @selected(($settings['payment']['hitpay_success_status_id'] ?? '') == $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
            </div>
        </div>
    </x-admin.panel>
</x-admin.form>

