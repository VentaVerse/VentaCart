@props(['title' => 'Settings', 'active' => null])
{{-- Settings wearing the shared space shell: this wrapper only
     resolves the settings registry and hands it over. --}}
@php
    $routeOk = fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']);
    $v3Groups = collect(config('admin3-settings-nav'))
        ->map(function ($group) use ($routeOk) {
            $group['items'] = collect($group['items'])
                ->filter($routeOk)
                ->map(fn ($i) => $i + ['url' => $i['section'] !== null ? route($i['route'], $i['section']) : route($i['route'])])
                ->values()
                ->all();
            return $group;
        })
        ->filter(fn ($group) => $group['items'] !== [])
        ->values()
        ->all();
@endphp
<x-admin3.space-shell :title="$title" space="Settings" :groups="$v3Groups" :active="$active"
                      rail-active="settings" :roots="[parse_url(route('admin.settings.index'), PHP_URL_PATH)]"
                      {{ $attributes }}>
    {{ $slot }}
</x-admin3.space-shell>
