@php
    // $panels: the blocks this group holds, each already drawn, with the
    // title its tab or section shows (App\Support\BlockGroup).
    $config = $placement->config ?? [];
    $panels = array_values($panels ?? []);
    $accordion = ($config['display'] ?? 'tabs') === 'accordion';
    $first = ! $accordion || ($config['open'] ?? 'first') === 'first' ? 0 : null;
    $uid = 'wgroup-' . ($placement->id ?? substr(md5(json_encode($config) . $placement->widget), 0, 8));
@endphp
@if ($panels !== [])
<section class="py-10 md:py-14">
    <div class="container-store" x-data="{ open: {{ $first === null ? 'null' : '0' }} }">
        @if ($placement->title)
            <h2 class="section-title reveal">{{ $placement->title }}</h2>
        @endif
        @if ($accordion)
            <div class="waccordion wgroup wgroup--accordion">
                @foreach ($panels as $i => $panel)
                    <div class="waccordion__item" :class="{ 'is-open': open === {{ $i }} }">
                        <h3 class="waccordion__head">
                            <button type="button" class="waccordion__btn" :aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                                    aria-controls="{{ $uid }}-{{ $i }}" @click="open = (open === {{ $i }} ? null : {{ $i }})">
                                <span>{{ $panel['title'] }}</span>
                                <span class="waccordion__mark" aria-hidden="true"></span>
                            </button>
                        </h3>
                        <div id="{{ $uid }}-{{ $i }}" class="wgroup__panel" x-show="open === {{ $i }}" x-transition @if ($i !== $first) x-cloak @endif>
                            {!! $panel['html'] !!}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="wgroup wgroup--tabs">
                <div class="wtabs__bar wgroup__bar" role="tablist">
                    @foreach ($panels as $i => $panel)
                        <button type="button" class="wtabs__tab" id="{{ $uid }}-tab-{{ $i }}" role="tab" aria-controls="{{ $uid }}-{{ $i }}"
                                :class="{ 'is-on': open === {{ $i }} }" :aria-selected="open === {{ $i }} ? 'true' : 'false'"
                                @click="open = {{ $i }}">{{ $panel['title'] }}</button>
                    @endforeach
                </div>
                @foreach ($panels as $i => $panel)
                    <div id="{{ $uid }}-{{ $i }}" class="wgroup__panel" role="tabpanel" aria-labelledby="{{ $uid }}-tab-{{ $i }}"
                         x-show="open === {{ $i }}" @if ($i > 0) x-cloak @endif>
                        {!! $panel['html'] !!}
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
