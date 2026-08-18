<x-admin3.shell title="Coupons">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.extensions.order-totals') }}">Order Totals</a>
                <span aria-hidden="true">/</span>
                <span>Coupons</span>
            </div>
            <h1 class="v3-head__title">Coupons</h1>
            <p class="v3-head__standing">Discount codes applied at checkout. Create and manage the codes under Marketing, Coupons.</p>
        </div>
        <div class="v3-head__actions">
            <a class="v3-btn v3-btn--secondary" href="{{ route('admin.coupons.index') }}">Manage coupons</a>
            <x-admin3.extension-switch name="coupons" />
        </div>
    </div>

    <x-admin3.form :action="route('admin.settings.update')">
        <input type="hidden" name="group" value="totals">
        <input type="hidden" name="_redirect" value="{{ route('admin.coupons.settings') }}">
        <div class="v3-settle">
            <x-admin3.panel title="Checkout"
                note="Total lines run lowest sort order first. Coupons before store credit lets the discount land before credit is drawn.">
                <x-admin3.field name="settings[coupon_sort_order]" label="Sort order">
                    <input type="number" name="settings[coupon_sort_order]" min="0" max="9999" step="1"
                           class="v3-control" value="{{ $sortOrder }}">
                </x-admin3.field>
            </x-admin3.panel>
        </div>
    </x-admin3.form>
</x-admin3.shell>
