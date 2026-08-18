<x-admin.shell title="Coupons">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.extensions.order-totals') }}">Order Totals</a>
                <span aria-hidden="true">/</span>
                <span>Coupons</span>
            </div>
            <h1 class="v-head__title">Coupons</h1>
            <p class="v-head__standing">Discount codes applied at checkout. Create and manage the codes under Marketing, Coupons.</p>
        </div>
        <div class="v-head__actions">
            <a class="v-btn v-btn--secondary" href="{{ route('admin.coupons.index') }}">Manage coupons</a>
            <x-admin.extension-switch name="coupons" />
        </div>
    </div>

    <x-admin.form :action="route('admin.settings.update')">
        <input type="hidden" name="group" value="totals">
        <input type="hidden" name="_redirect" value="{{ route('admin.coupons.settings') }}">
        <div class="v-settle">
            <x-admin.panel title="Checkout"
                note="Total lines run lowest sort order first. Coupons before store credit lets the discount land before credit is drawn.">
                <x-admin.field name="settings[coupon_sort_order]" label="Sort order">
                    <input type="number" name="settings[coupon_sort_order]" min="0" max="9999" step="1"
                           class="v-control" value="{{ $sortOrder }}">
                </x-admin.field>
            </x-admin.panel>
        </div>
    </x-admin.form>
</x-admin.shell>
