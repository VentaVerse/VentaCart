{{-- Store promises: three or four short promises with an icon, in a strip, columns or centered. --}}
@php
    $icons = ['truck', 'cash', 'shield-check', 'rotate-ccw', 'package', 'tag', 'bolt', 'wrench', 'clock', 'chat', 'map-pin', 'star', 'heart', 'phone', 'mail', 'check'];
@endphp
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
         data-icon-style="{{ $settings['icon_style'] }}" data-count="{{ count($blocks) }}" aria-label="{{ $section['label'] }}">
    @include('xelga::snippets.section-bg')
    <div class="s__inner">
        <ul class="usp">
            @foreach ($blocks as $block)
                @php
                    $s = $block['settings'];
                    $icon = in_array($s['icon'] ?? '', $icons, true) ? $s['icon'] : 'check';
                    $url = \Themes\Xelga\Settings\LinkValue::resolve($s['link'] ?? null);
                    $tag = $url ? 'a' : 'div';
                @endphp
                <li class="usp__item" data-block-id="{{ $block['id'] }}" data-block-type="promise" @if ($block['disabled']) hidden @endif>
                    <{{ $tag }} class="usp__link" @if ($url) href="{{ $url }}" @endif>
                        <span class="usp__icon"><svg class="icon" aria-hidden="true"><use href="#i-{{ $icon }}"></use></svg></span>
                        <span class="usp__text">
                            <span class="usp__title" data-setting="title">{{ $s['title'] }}</span>
                            <span class="usp__sub" data-setting="text" data-optional @if (($s['text'] ?? '') === '') hidden @endif>{{ $s['text'] }}</span>
                        </span>
                    </{{ $tag }}>
                </li>
            @endforeach
        </ul>
    </div>
</section>
