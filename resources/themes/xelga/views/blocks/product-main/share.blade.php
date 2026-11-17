{{-- Share: links to share the product's address; nothing loads from those sites until a shopper clicks. --}}
@php
    $s = $block['settings'];
    $url = rawurlencode($data['share_url']);
    $image = $data['images'][0]['src'] ?? null;
    $pin = 'https://www.pinterest.com/pin/create/button/?url=' . $url . ($image ? '&media=' . rawurlencode(url($image)) : '') . '&description=' . rawurlencode($product->name);
    $any = ($s['facebook'] ?? true) || ($s['pinterest'] ?? true) || ($s['copy'] ?? true);
@endphp
@if ($any || $context->preview)
    <div class="share" data-block-id="{{ $block['id'] }}" data-block-type="share" @if ($block['disabled'] || ! $any) hidden @endif>
        <span class="share__label">Share</span>
        @if ($s['facebook'] ?? true)
            <a class="icon-btn icon-btn--outline" href="https://www.facebook.com/sharer/sharer.php?u={{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="Share on Facebook (opens a new tab)"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-facebook"></use></svg></a>
        @endif
        @if ($s['pinterest'] ?? true)
            <a class="icon-btn icon-btn--outline" href="{{ $pin }}" target="_blank" rel="noopener noreferrer" aria-label="Save to Pinterest (opens a new tab)"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-pinterest"></use></svg></a>
        @endif
        @if ($s['copy'] ?? true)
            <button type="button" class="icon-btn icon-btn--outline" data-copy-link="{{ $data['share_url'] }}" aria-label="Copy link to this product"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-link"></use></svg></button>
        @endif
    </div>
@endif
