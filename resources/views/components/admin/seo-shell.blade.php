{{--
    The SEO workspace is the ordinary page skeleton wearing the
    workspace rail, exactly as Settings is.

    Both used to hand-roll the rail: two copies of the same sticky
    column and the same mobile <select>, already drifted apart (44px
    of stick against 54px). This turns the SEO registry into the
    shape x-admin.workspace speaks and nothing else.

    The registry is local on purpose. The one crossing into Settings
    is the "Engine settings" item below; the reverse never happens,
    because a Settings page must not hand out a foreign workspace's
    whole menu.
--}}
@props(['title', 'heading' => null])
@php
    $vSeoGroups = [
        ['label' => 'Work', 'items' => [
            ['label' => 'Dashboard', 'route' => 'admin.extensions.seo.dashboard', 'pattern' => 'admin.extensions.seo.dashboard'],
            ['label' => 'Review', 'route' => 'admin.extensions.seo.review', 'pattern' => 'admin.extensions.seo.review'],
            ['label' => 'Slugs', 'route' => 'admin.extensions.seo.slugs', 'pattern' => 'admin.extensions.seo.slugs'],
        ]],
        ['label' => 'Content', 'items' => [
            ['label' => 'Products', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'products']],
            ['label' => 'Categories', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'categories']],
            ['label' => 'Brands', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'brands']],
            ['label' => 'Blog', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'blog']],
            ['label' => 'Pages', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'pages']],
        ]],
        ['label' => 'Traffic', 'items' => [
            ['label' => 'Search Console', 'route' => 'admin.extensions.seo.search-console', 'pattern' => 'admin.extensions.seo.search-console'],
            ['label' => 'Redirects', 'route' => 'admin.extensions.seo.redirects.index', 'pattern' => 'admin.extensions.seo.redirects.*'],
            ['label' => '404s', 'route' => 'admin.extensions.seo.not-found.index', 'pattern' => 'admin.extensions.seo.not-found.*'],
        ]],
        ['label' => 'Setup', 'items' => [
            ['label' => 'Strategy', 'route' => 'admin.extensions.seo.strategy', 'pattern' => 'admin.extensions.seo.strategy'],
            ['label' => 'Engine settings', 'route' => 'admin.extensions.seo.settings', 'pattern' => 'admin.extensions.seo.settings'],
        ]],
    ];

    $vSeoGroups = collect($vSeoGroups)->map(function (array $group) {
        $group['items'] = collect($group['items'])->map(function (array $item) {
            $active = isset($item['params']['type'])
                ? request()->routeIs('admin.extensions.seo.entities') && request()->route('type') === $item['params']['type']
                : request()->routeIs($item['pattern']);

            return [
                'label' => $item['label'],
                'url' => route($item['route'], $item['params'] ?? []),
                'active' => $active,
            ];
        })->all();

        return $group;
    })->all();
@endphp
<x-admin.shell :title="$title">
    <x-admin.page :title="$heading ?? $title">
        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset
        <x-admin.workspace :groups="$vSeoGroups" label="SEO workspace">
            {{ $slot }}
        </x-admin.workspace>
    </x-admin.page>
</x-admin.shell>
