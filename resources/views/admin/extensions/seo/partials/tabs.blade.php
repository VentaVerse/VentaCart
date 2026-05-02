@php
    $tabs = [
        ['route' => 'admin.extensions.seo.dashboard', 'label' => 'Dashboard', 'pattern' => 'admin.extensions.seo.dashboard'],
        ['route' => 'admin.extensions.seo.entities', 'routeParams' => ['type' => 'products'], 'label' => 'Products', 'active' => ($type ?? '') === 'products'],
        ['route' => 'admin.extensions.seo.entities', 'routeParams' => ['type' => 'categories'], 'label' => 'Categories', 'active' => ($type ?? '') === 'categories'],
        ['route' => 'admin.extensions.seo.entities', 'routeParams' => ['type' => 'brands'], 'label' => 'Brands', 'active' => ($type ?? '') === 'brands'],
        ['route' => 'admin.extensions.seo.entities', 'routeParams' => ['type' => 'blog'], 'label' => 'Blog', 'active' => ($type ?? '') === 'blog'],
        ['route' => 'admin.extensions.seo.entities', 'routeParams' => ['type' => 'pages'], 'label' => 'Pages', 'active' => ($type ?? '') === 'pages'],
        ['route' => 'admin.extensions.seo.review', 'label' => 'Review', 'pattern' => 'admin.extensions.seo.review'],
        ['route' => 'admin.extensions.seo.slugs', 'label' => 'Slugs', 'pattern' => 'admin.extensions.seo.slugs'],
        ['route' => 'admin.extensions.seo.search-console', 'label' => 'Search Console', 'pattern' => 'admin.extensions.seo.search-console'],
        ['route' => 'admin.extensions.seo.redirects.index', 'label' => 'Redirects', 'pattern' => 'admin.extensions.seo.redirects.*'],
        ['route' => 'admin.extensions.seo.not-found.index', 'label' => '404s', 'pattern' => 'admin.extensions.seo.not-found.*'],
        ['route' => 'admin.extensions.seo.strategy', 'label' => 'Strategy', 'pattern' => 'admin.extensions.seo.strategy'],
        ['route' => 'admin.extensions.seo.settings', 'label' => 'Settings', 'pattern' => 'admin.extensions.seo.settings'],
    ];
@endphp
<div class="border-b border-gray-200 mb-6">
    <nav class="flex gap-6 overflow-x-auto">
        @foreach ($tabs as $tab)
            @php
                $isActive = isset($tab['active']) ? $tab['active'] : (isset($tab['pattern']) && request()->routeIs($tab['pattern']));
                $url = route($tab['route'], $tab['routeParams'] ?? []);
            @endphp
            <a href="{{ $url }}" class="pb-3 text-sm font-medium border-b-2 whitespace-nowrap transition-colors {{ $isActive ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
