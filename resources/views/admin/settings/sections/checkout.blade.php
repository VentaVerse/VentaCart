<x-admin.settings-shell title="Checkout & Stock" active="checkout">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Checkout & Stock</h1>
            <p class="v-head__standing">Who may check out, and what happens when stock runs low.</p>
        </div>
    </div>

    <x-admin.form :action="route('admin.settings.update')" class="v-settle">
        <input type="hidden" name="_redirect" value="{{ route('admin.settings.section', 'checkout') }}">

        <x-admin.panel title="Checkout">
            <label class="v-toggle">
                <input type="hidden" name="groups[checkout][guest_allowed]" value="0">
                <input type="checkbox" name="groups[checkout][guest_allowed]" value="1"
                       @checked(($settings['checkout']['guest_allowed'] ?? '0') === '1')>
                <span class="v-toggle__track" aria-hidden="true"></span>
                Allow guest checkout
            </label>
            <div class="v-gap">
                <x-admin.field name="groups.checkout.block_out_of_stock">
                    <label class="v-toggle">
                        <input type="hidden" name="groups[checkout][block_out_of_stock]" value="0">
                        <input type="checkbox" name="groups[checkout][block_out_of_stock]" value="1"
                               @checked(($settings['checkout']['block_out_of_stock'] ?? '1') === '1')>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Block checkout for out-of-stock items
                        <x-admin.help text="A cart that went out of stock after the item was added is blocked too." />
                    </label>
                </x-admin.field>
            </div>
        </x-admin.panel>

        {{-- Two purpose-named checkout messages. The message to the store
             is the classic order comment; the message to the courier is what
             fulfillment hands the rider at booking. Labels follow the
             settings-as-fallback pattern: blank uses the default. --}}
        <x-admin.panel title="Order messages">
            <label class="v-toggle">
                <input type="hidden" name="groups[checkout][comment_store_enabled]" value="0">
                <input type="checkbox" name="groups[checkout][comment_store_enabled]" value="1"
                       @checked(($settings['checkout']['comment_store_enabled'] ?? '1') === '1')>
                <span class="v-toggle__track" aria-hidden="true"></span>
                Message to Store
                <x-admin.help text="A note for whoever packs the order (please pack carefully). Stored on the order and shown in admin and order emails." />
            </label>
            <div class="v-gap-sm">
                <x-admin.field name="groups.checkout.comment_store_label" label="Field label"
                    hint="What the shopper sees above the box. Blank uses the default.">
                    <input type="text" name="groups[checkout][comment_store_label]" class="v-control v-w-name"
                           placeholder="Message to Store"
                           value="{{ $settings['checkout']['comment_store_label'] ?? '' }}">
                </x-admin.field>
            </div>

            <div class="v-gap">
                <label class="v-toggle">
                    <input type="hidden" name="groups[checkout][comment_courier_enabled]" value="0">
                    <input type="checkbox" name="groups[checkout][comment_courier_enabled]" value="1"
                           @checked(($settings['checkout']['comment_courier_enabled'] ?? '0') === '1')>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Message to Courier
                    <x-admin.help text="A note for the rider (deliver on these dates). Stored on the order; a fulfillment module passes it to the courier when booking." />
                </label>
            </div>
            <div class="v-gap-sm">
                <x-admin.field name="groups.checkout.comment_courier_label" label="Field label"
                    hint="What the shopper sees above the box. Blank uses the default.">
                    <input type="text" name="groups[checkout][comment_courier_label]" class="v-control v-w-name"
                           placeholder="Message to Courier"
                           value="{{ $settings['checkout']['comment_courier_label'] ?? '' }}">
                </x-admin.field>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Low-stock alerts">
            <label class="v-toggle">
                <input type="hidden" name="groups[stock][low_stock_alert_enabled]" value="0">
                <input type="checkbox" name="groups[stock][low_stock_alert_enabled]" value="1"
                       @checked(($settings['stock']['low_stock_alert_enabled'] ?? '0') === '1')>
                <span class="v-toggle__track" aria-hidden="true"></span>
                Email an alert when stock runs low
            </label>
            <div class="v-grid v-grid--2 v-gap">
                <x-admin.field name="groups.stock.low_stock_threshold" label="Stock threshold" hint="Alert when a product's stock drops to or below this number.">
                    <input type="number" name="groups[stock][low_stock_threshold]" min="1" max="100" class="v-control v-mono"
                           value="{{ $settings['stock']['low_stock_threshold'] ?? 5 }}">
                </x-admin.field>
                <x-admin.field name="groups.stock.low_stock_alert_email" label="Send alerts to" hint="Blank sends to the admin email.">
                    <input type="email" name="groups[stock][low_stock_alert_email]" class="v-control"
                           placeholder="{{ auth()->user()->email }}"
                           value="{{ $settings['stock']['low_stock_alert_email'] ?? '' }}">
                </x-admin.field>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.settings-shell>
