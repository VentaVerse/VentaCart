{{-- Delivery and payment notes: up to three short lines the merchant writes. --}}
@php
    $s = $block['settings'];
    $icons = ['truck', 'cash', 'rotate-ccw', 'shield-check', 'package', 'clock', 'map-pin', 'tag', 'chat', 'phone', 'check', 'info'];
    $lines = [];
    foreach ([1, 2, 3] as $n) {
        $title = trim((string) ($s["title_{$n}"] ?? ''));
        $text = trim((string) ($s["text_{$n}"] ?? ''));
        if ($title !== '' || $text !== '') {
            $lines[] = ['n' => $n, 'icon' => in_array($s["icon_{$n}"] ?? '', $icons, true) ? $s["icon_{$n}"] : 'check', 'title' => $title, 'text' => $text];
        }
    }
@endphp
@if ($lines !== [] || $context->preview)
    <ul class="perks" data-block-id="{{ $block['id'] }}" data-block-type="delivery-note" @if ($block['disabled'] || $lines === []) hidden @endif>
        @foreach ($lines as $line)
            <li><svg class="icon" aria-hidden="true"><use href="#i-{{ $line['icon'] }}"></use></svg><span><strong data-setting="title_{{ $line['n'] }}">{{ $line['title'] }}</strong> <span data-setting="text_{{ $line['n'] }}">{{ $line['text'] }}</span></span></li>
        @endforeach
    </ul>
@endif
