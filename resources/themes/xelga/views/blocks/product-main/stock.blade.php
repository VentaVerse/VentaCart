{{-- Stock note: in stock, only a few left (core's low stock level), or sold out. --}}
@php
    $stock = $data['stock'];
    $showLow = (bool) ($block['settings']['show_low'] ?? true);
    $text = match (true) {
        $stock['level'] === 'out' => 'Sold out',
        $stock['level'] === 'low' && $showLow => 'Only ' . $stock['count'] . ' left',
        default => 'In stock, ready to ship',
    };
@endphp
@if (! $data['unavailable'])
    <p class="stock-note" data-block-id="{{ $block['id'] }}" data-block-type="stock" data-stock-note data-show-low="{{ $showLow ? 'true' : 'false' }}"
       data-level="{{ $stock['level'] === 'low' && ! $showLow ? 'in' : $stock['level'] }}" @if ($block['disabled']) hidden @endif>{{ $text }}</p>
@endif
