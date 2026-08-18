<x-admin.shell title="Store Credit">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.extensions.order-totals') }}">Order Totals</a>
                <span aria-hidden="true">/</span>
                <span>Store Credit</span>
            </div>
            <h1 class="v-head__title">Store Credit</h1>
            <p class="v-head__standing">Applies itself against the order total at checkout. Grant or deduct credit from each customer's own page.</p>
        </div>
        <div class="v-head__actions">
            <a class="v-btn v-btn--secondary" href="{{ route('admin.store-credit.ledger') }}">Ledger</a>
            <x-admin.extension-switch name="store-credit" />
        </div>
    </div>

    <x-admin.form :action="route('admin.settings.update')">
        <input type="hidden" name="group" value="totals">
        <input type="hidden" name="_redirect" value="{{ route('admin.store-credit.index') }}">
        <div class="v-settle">
            <x-admin.panel title="Checkout"
                note="Total lines run lowest sort order first. Credit after coupons stretches a balance further.">
                <x-admin.field name="settings[store_credit_sort_order]" label="Sort order">
                    <input type="number" name="settings[store_credit_sort_order]" min="0" max="9999" step="1"
                           class="v-control" value="{{ $sortOrder }}">
                </x-admin.field>
            </x-admin.panel>
        </div>
    </x-admin.form>
</x-admin.shell>
