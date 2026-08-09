{{--
    Grouped SEO workspace nav — settings-shell's visual pattern (sticky grouped
    sidebar + mobile <select>), scoped locally to the SEO extension instead of
    App\Support\SettingsNav. Replaces the old flat 13-tab strip (formerly
    partials/tabs.blade.php, deleted) across every SEO page.
--}}
@php
    $seoNavGroups = [
        [
            'label' => 'Work',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'admin.extensions.seo.dashboard', 'pattern' => 'admin.extensions.seo.dashboard'],
                ['label' => 'Review', 'route' => 'admin.extensions.seo.review', 'pattern' => 'admin.extensions.seo.review'],
                ['label' => 'Slugs', 'route' => 'admin.extensions.seo.slugs', 'pattern' => 'admin.extensions.seo.slugs'],
            ],
        ],
        [
            'label' => 'Content',
            'items' => [
                ['label' => 'Products', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'products']],
                ['label' => 'Categories', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'categories']],
                ['label' => 'Brands', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'brands']],
                ['label' => 'Blog', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'blog']],
                ['label' => 'Pages', 'route' => 'admin.extensions.seo.entities', 'params' => ['type' => 'pages']],
            ],
        ],
        [
            'label' => 'Traffic',
            'items' => [
                ['label' => 'Search Console', 'route' => 'admin.extensions.seo.search-console', 'pattern' => 'admin.extensions.seo.search-console'],
                ['label' => 'Redirects', 'route' => 'admin.extensions.seo.redirects.index', 'pattern' => 'admin.extensions.seo.redirects.*'],
                ['label' => '404s', 'route' => 'admin.extensions.seo.not-found.index', 'pattern' => 'admin.extensions.seo.not-found.*'],
            ],
        ],
        [
            'label' => 'Setup',
            'items' => [
                ['label' => 'Strategy', 'route' => 'admin.extensions.seo.strategy', 'pattern' => 'admin.extensions.seo.strategy'],
                // Single, explicit crossing into the Settings context (modules/seo/settings)
                // — never the reverse (workspace nav must not bleed into Settings' own pages).
                ['label' => 'Engine settings', 'route' => 'admin.extensions.seo.settings', 'pattern' => 'admin.extensions.seo.settings'],
            ],
        ],
    ];

    $seoNavUrl = fn (array $item) => route($item['route'], $item['params'] ?? []);

    $seoNavActive = function (array $item) {
        if (isset($item['params']['type'])) {
            return request()->routeIs('admin.extensions.seo.entities')
                && request()->route('type') === $item['params']['type'];
        }

        return request()->routeIs($item['pattern']);
    };
@endphp
<nav class="hidden lg:block w-[13.5rem] shrink-0" aria-label="SEO workspace">
    <div class="sticky top-11 space-y-4">
        @foreach ($seoNavGroups as $group)
            <div>
                <p class="mb-1 px-2 v-label">{{ $group['label'] }}</p>
                <div class="space-y-0.5">
                    @foreach ($group['items'] as $item)
                        <a href="{{ $seoNavUrl($item) }}"
                           class="block rounded-v-md px-2 py-1 text-[13px] transition-colors
                               {{ $seoNavActive($item) ? 'bg-v-accent-weak font-semibold text-v-accent' : 'text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</nav>
<select x-data @change="window.location.assign($event.target.value)"
        class="v-control lg:hidden mb-5 w-full h-[var(--control-h)] rounded-v-md border border-v-border bg-v-surface px-2.5 text-[13px] text-v-text" aria-label="SEO section">
    @foreach ($seoNavGroups as $group)
        <optgroup label="{{ $group['label'] }}">
            @foreach ($group['items'] as $item)
                <option value="{{ $seoNavUrl($item) }}" @selected($seoNavActive($item))>{{ $item['label'] }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
