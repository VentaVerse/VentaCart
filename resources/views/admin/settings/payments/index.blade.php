<x-admin3.shell title="Payments">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Payments</h1>
            <p class="v3-head__standing">How buyers pay. Where a method may appear lives in its page's Availability tab.</p>
        </div>
    </div>

    {{-- Every row is an extension wearing the standard button set
         (wave 2). --}}
    <div class="v3-settle">
        @if ($noMethodEnabled)
            <div class="v3-notice">
                <p class="v3-notice__title">No payment method is enabled</p>
                <p class="v3-notice__text">The storefront cannot check out until a method below is enabled.</p>
            </div>
        @endif

        <div class="v3-dir">
            @foreach ($cards as $module)
                <div class="v3-dir__card v3-dir__card--static">
                    <span class="v3-dir__ico"><x-admin3.icon :name="$module['name'] === 'payment-cod' ? 'cart' : 'payments'" /></span>
                    <span class="v3-dir__body">
                        <span class="v3-dir__name">{{ $module['title'] }}</span>
                        <span class="v3-dir__line v3-block">{{ $module['description'] }}</span>
                    </span>
                    @include('admin.extensions.partials.lifecycle-actions')
                </div>
            @endforeach
        </div>
    </div>
</x-admin3.shell>
