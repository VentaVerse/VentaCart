@props(['title', 'active' => null])
{{-- The SEO space wearing the shared space shell. --}}
@php
    $routeOk = fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']);
    $v3SeoGroups = collect(config('admin3-seo-nav'))
        ->map(function ($group) use ($routeOk) {
            $group['items'] = collect($group['items'])
                ->filter($routeOk)
                ->map(fn ($i) => $i + ['url' => route($i['route'], $i['params'] ?? [])])
                ->values()
                ->all();
            return $group;
        })
        ->filter(fn ($group) => $group['items'] !== [])
        ->values()
        ->all();
@endphp
<x-admin3.space-shell :title="$title" space="SEO" :groups="$v3SeoGroups" :active="$active"
                      rail-active="marketing" :roots="[parse_url(route('admin.extensions.seo.dashboard'), PHP_URL_PATH), parse_url(route('admin.extensions.seo.settings'), PHP_URL_PATH)]"
                      {{ $attributes }}>
    {{ $slot }}
</x-admin3.space-shell>
