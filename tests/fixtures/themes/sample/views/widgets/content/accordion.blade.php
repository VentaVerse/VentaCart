@php
    $config = $placement->config ?? [];
    $items = collect(is_array($config['items'] ?? null) ? $config['items'] : [])->filter(fn ($i) => is_array($i) && ($i['title'] ?? '') !== '')->values();
    $first = ($config['open'] ?? 'first') === 'first' ? 0 : null;
@endphp
@if ($items->isNotEmpty())
<section class="py-10 md:py-14">
    <div class="container-store">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        <div class="waccordion reveal" x-data="{ open: {{ $first === null ? 'null' : '0' }} }">
            @foreach ($items as $i => $item)
                <div class="waccordion__item" :class="{ 'is-open': open === {{ $i }} }">
                    <h3 class="waccordion__head">
                        <button type="button" class="waccordion__btn" :aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                                aria-controls="acc-{{ $placement->id }}-{{ $i }}" @click="open = (open === {{ $i }} ? null : {{ $i }})">
                            <span>{{ $item['title'] }}</span>
                            <span class="waccordion__mark" aria-hidden="true"></span>
                        </button>
                    </h3>
                    <div id="acc-{{ $placement->id }}-{{ $i }}" class="waccordion__body" x-show="open === {{ $i }}" x-transition @if ($i !== $first) x-cloak @endif>
                        <p>{!! nl2br(e($item['text'] ?? '')) !!}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
