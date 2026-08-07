{{--
    'breadcrumb' is optional: omit it entirely and the trail is derived from
    the request path via App\Support\Breadcrumbs (Task 7 — the URL grammar IS
    the navigation). Pass it explicitly only for trails a URL can't encode on
    its own (a cross-reference to a sibling page, a preserved tab/filter query
    param, a dynamic multi-level parent chain). Pass an explicit empty array
    to suppress the breadcrumb entirely.
--}}
@props(['title', 'breadcrumb' => null])
@php $crumbs = $breadcrumb ?? \App\Support\Breadcrumbs::forPath(request()->path(), $title); @endphp
<div>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            @if ($crumbs !== [])
                <nav class="mb-0.5 flex items-center gap-1 text-xs text-v-muted">
                    @foreach ($crumbs as $crumb)
                        @if (isset($crumb['url']))<a href="{{ $crumb['url'] }}" class="hover:text-v-secondary">{{ $crumb['label'] }}</a>
                        @else<span>{{ $crumb['label'] }}</span>@endif
                        <x-admin.icon name="chevron-right" class="w-3 h-3" />
                    @endforeach
                </nav>
            @endif
            <h1 class="text-lg font-semibold text-v-text">{{ $title }}</h1>
        </div>
        @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
    </div>
    {{ $slot }}
</div>
