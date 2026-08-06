@props(['title', 'breadcrumb' => []])
<div>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            @if ($breadcrumb !== [])
                <nav class="mb-0.5 flex items-center gap-1 text-xs text-v-muted">
                    @foreach ($breadcrumb as $crumb)
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
