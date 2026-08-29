<x-admin.shell title="Payments">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Payments</h1>
            <p class="v-head__standing">How buyers pay. Where a method may appear lives in its page's Availability tab.</p>
        </div>
    </div>

    {{-- Every row is an extension wearing the standard button set
         (wave 2). --}}
    <div class="v-settle">
        @if ($noMethodEnabled)
            <div class="v-notice">
                <p class="v-notice__title">No payment method is enabled</p>
                <p class="v-notice__text">The storefront cannot check out until a method below is enabled.</p>
            </div>
        @endif

        <div class="v-dir">
            @foreach ($cards as $module)
                <div class="v-dir__card v-dir__card--static">
                    <span class="v-dir__ico"><x-admin.icon :name="$module['name'] === 'payment-cod' ? 'cart' : 'payments'" /></span>
                    <span class="v-dir__body">
                        <span class="v-dir__name">{{ $module['title'] }}</span>
                        <span class="v-dir__line v-block">{{ $module['description'] }}</span>
                        @if (in_array($module['name'], $needsStatus ?? [], true))
                            {{-- Enabled, but not offered at checkout until its page names
                                 the status an order is born with. --}}
                            <span class="v-badge v-badge--warn">No order status selected</span>
                        @endif
                    </span>
                    @include('admin.extensions.partials.lifecycle-actions')
                </div>
            @endforeach
        </div>
    </div>
</x-admin.shell>
