@props(['title' => null, 'note' => null, 'flush' => false])
<section class="v3-panel" {{ $attributes }}>
    @if ($title || isset($acts))
        <div class="v3-panel__head">
            <div>
                @if ($title)
                    <h2 class="v3-panel__title">
                        {{ $title }}
                        @if ($note)<x-admin3.help :text="$note" />@endif
                    </h2>
                @endif
            </div>
            @isset($acts)
                <div class="v3-panel__acts">{{ $acts }}</div>
            @endisset
        </div>
    @endif
    <div class="{{ $flush ? 'v3-panel__body--flush' : 'v3-panel__body' }}">
        {{ $slot }}
    </div>
</section>
