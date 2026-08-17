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
                <x-admin3.field name="groups.checkout.block_out_of_stock">
                    <label class="v3-toggle">
                        <input type="hidden" name="groups[checkout][block_out_of_stock]" value="0">
                        <input type="checkbox" name="groups[checkout][block_out_of_stock]" value="1"
                               @checked(($settings['checkout']['block_out_of_stock'] ?? '1') === '1')>
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        Block checkout for out-of-stock items
                        <x-admin3.help text="A cart that went out of stock after the item was added is blocked too." />
                    </label>
                </x-admin3.field>
            </div>
        </x-admin3.panel>

        {{-- Two purpose-named checkout messages. The message to the store
             is the classic order comment; the message to the courier is what
             fulfillment hands the rider at booking. Labels follow the
             settings-as-fallback pattern: blank uses the default. --}}
        <x-admin3.panel title="Order messages">
            <label class="v3-toggle">
                <input type="hidden" name="groups[checkout][comment_store_enabled]" value="0">
                <input type="checkbox" name="groups[checkout][comment_store_enabled]" value="1"
                       @checked(($settings['checkout']['comment_store_enabled'] ?? '1') === '1')>
                <span class="v3-toggle__track" aria-hidden="true"></span>
                Message to Store
                <x-admin3.help text="A note for whoever packs the order (please pack carefully). Stored on the order and shown in admin and order emails." />
            </label>
            <div class="v3-gap-sm">
                <x-admin3.field name="groups.checkout.comment_store_label" label="Field label"
                    hint="What the shopper sees above the box. Blank uses the default.">
                    <input type="text" name="groups[checkout][comment_store_label]" class="v3-control v3-w-name"
                           placeholder="Message to Store"
                           value="{{ $settings['checkout']['comment_store_label'] ?? '' }}">
                </x-admin3.field>
            </div>

            <div class="v3-gap">
                <label class="v3-toggle">
                    <input type="hidden" name="groups[checkout][comment_courier_enabled]" value="0">
                    <input type="checkbox" name="groups[checkout][comment_courier_enabled]" value="1"
                           @checked(($settings['checkout']['comment_courier_enabled'] ?? '0') === '1')>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Message to Courier
                    <x-admin3.help text="A note for the rider (deliver on these dates). Stored on the order; a fulfillment module passes it to the courier when booking." />
                </label>
            </div>
            <div class="v3-gap-sm">
                <x-admin3.field name="groups.checkout.comment_courier_label" label="Field label"
                    hint="What the shopper sees above the box. Blank uses the default.">
                    <input type="text" name="groups[checkout][comment_courier_label]" class="v3-control v3-w-name"
                           placeholder="Message to Courier"
                           value="{{ $settings['checkout']['comment_courier_label'] ?? '' }}">
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
