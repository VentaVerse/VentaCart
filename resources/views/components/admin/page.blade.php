{{--
    The admin's one page head.

    'breadcrumb' is optional: omit it entirely and the trail is derived from
    the request path via App\Support\Breadcrumbs (Task 7 — the URL grammar IS
    the navigation). Pass it explicitly only for trails a URL can't encode on
    its own (a cross-reference to a sibling page, a preserved tab/filter query
    param, a dynamic multi-level parent chain). Pass an explicit empty array
    to suppress the breadcrumb entirely.

    'standing' is optional and is a reading, never an instruction: one line
    saying where the page stands, composed from figures the page already has.

    Any class passed to this component lands on the page's root, which is how
    a page opts into `.v-day` (the flow column) and `.v-settle` (the stagger).
--}}
@props(['title', 'breadcrumb' => null, 'standing' => null])
@php $crumbs = $breadcrumb ?? \App\Support\Breadcrumbs::forPath(request()->path(), $title); @endphp
<div {{ $attributes }}>
    <header class="v-day__head">
        <div class="v-day__headtext">
            @if ($crumbs !== [])
                <nav class="v-day__trail" aria-label="Breadcrumb">
                    @foreach ($crumbs as $crumb)
                        @if (isset($crumb['url']))<a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                        @else<span>{{ $crumb['label'] }}</span>@endif
                        <x-admin.icon name="chevron-right" class="w-3 h-3" />
                    @endforeach
                </nav>
            @endif
            <h1 class="v-day__title">{{ $title }}</h1>
            @if ($standing)<p class="v-day__standing">{{ $standing }}</p>@endif
        </div>
        @isset($actions)<div class="v-day__actions">{{ $actions }}</div>@endisset
    </header>
    {{ $slot }}
</div>
