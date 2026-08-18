@props(['title' => null, 'note' => null, 'flush' => false])
<section class="v-panel" {{ $attributes }}>
    @if ($title || isset($acts))
        <div class="v-panel__head">
            <div>
                @if ($title)
                    <h2 class="v-panel__title">
                        {{ $title }}
                        @if ($note)<x-admin.help :text="$note" />@endif
                    </h2>
                @endif
            </div>
            @isset($acts)
                <div class="v-panel__acts">{{ $acts }}</div>
            @endisset
        </div>
    @endif
    <div class="{{ $flush ? 'v-panel__body--flush' : 'v-panel__body' }}">
        {{ $slot }}
    </div>
</section>
