<x-admin3.settings-shell title="Checkout & Stock" active="checkout">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Checkout & Stock</h1>
            <p class="v3-head__standing">Who may check out, and what happens when stock runs low.</p>
        </div>
    </div>

    <x-admin3.form :action="route('admin.settings.update')" class="v3-settle">
        <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'checkout') }}">

        <x-admin3.panel title="Checkout">
            <label class="v3-toggle">
                <input type="hidden" name="groups[checkout][guest_allowed]" value="0">
                <input type="checkbox" name="groups[checkout][guest_allowed]" value="1"
                       @checked(($settings['checkout']['guest_allowed'] ?? '0') === '1')>
                <span class="v3-toggle__track" aria-hidden="true"></span>
                Allow guest checkout
            </label>
            <div class="v3-gap">
                <x-admin3.field name="groups.checkout.block_out_of_stock" hint="A cart that went out of stock after the item was added is blocked too.">
                    <label class="v3-toggle">
                        <input type="hidden" name="groups[checkout][block_out_of_stock]" value="0">
                        <input type="checkbox" name="groups[checkout][block_out_of_stock]" value="1"
                               @checked(($settings['checkout']['block_out_of_stock'] ?? '1') === '1')>
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        Block checkout for out-of-stock items
                    </label>
                </x-admin3.field>
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="Low-stock alerts">
            <label class="v3-toggle">
                <input type="hidden" name="groups[stock][low_stock_alert_enabled]" value="0">
                <input type="checkbox" name="groups[stock][low_stock_alert_enabled]" value="1"
                       @checked(($settings['stock']['low_stock_alert_enabled'] ?? '0') === '1')>
                <span class="v3-toggle__track" aria-hidden="true"></span>
                Email an alert when stock runs low
            </label>
            <div class="v3-grid v3-grid--2 v3-gap">
                <x-admin3.field name="groups.stock.low_stock_threshold" label="Stock threshold" hint="Alert when a product's stock drops to or below this number.">
                    <input type="number" name="groups[stock][low_stock_threshold]" min="1" max="100" class="v3-control v3-mono"
                           value="{{ $settings['stock']['low_stock_threshold'] ?? 5 }}">
                </x-admin3.field>
                <x-admin3.field name="groups.stock.low_stock_alert_email" label="Send alerts to" hint="Blank sends to the admin email.">
                    <input type="email" name="groups[stock][low_stock_alert_email]" class="v3-control"
                           placeholder="{{ auth()->user()->email }}"
                           value="{{ $settings['stock']['low_stock_alert_email'] ?? '' }}">
                </x-admin3.field>
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.settings-shell>
