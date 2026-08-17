<x-admin3.shell :title="$name">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.payment-settings.index') }}">Payments</a>
                <span aria-hidden="true">/</span>
                <span>{{ $name }}</span>
            </div>
            <h1 class="v3-head__title">{{ $name }}</h1>
        </div>
    </div>

    <div x-data="{ tab: @js(request('tab', 'config')),
            pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); } }">
        <div class="v3-tabs" role="tablist">
            <button type="button" class="v3-tab" role="tab" :aria-selected="tab === 'config' ? 'true' : 'false'" @click="pick('config')">Configuration</button>
            <button type="button" class="v3-tab" role="tab" :aria-selected="tab === 'availability' ? 'true' : 'false'" @click="pick('availability')">Availability</button>
        </div>

        <div x-show="tab === 'config'" class="v3-settle">
            @include('admin.settings.payments.partials.config-' . str_replace('_', '-', $method))
        </div>

        <div x-show="tab === 'availability'" x-cloak class="v3-settle">
            @include('admin.settings.payments.partials.availability')
        </div>
    </div>
</x-admin3.shell>
