{{--
    Volume pricing: the price per piece at each quantity where this
    shopper's customer group has a special in core. Shoppers whose group has
    none see nothing; the prices are what the cart charges.
--}}
@if ($data['tiers'] !== [] || $context->preview)
    <div class="tiers" data-block-id="{{ $block['id'] }}" data-block-type="volume-pricing" data-tiers @if ($block['disabled'] || $data['tiers'] === []) hidden @endif>
        @if ($data['tiers'] === [])
            @include('xelga::snippets.editor-note', ['note' => 'Shown only to shoppers whose customer group has volume prices for this product.'])
        @else
            <table class="tiers__table">
                <caption>{{ $block['settings']['heading'] ?? 'Volume pricing' }}</caption>
                <tbody>
                    @foreach ($data['tiers'] as $tier)
                        <tr data-tier="{{ $tier['qty'] }}"><th scope="row">{{ $tier['label'] }} pieces</th><td data-tier-price>{{ $tier['price'] }} each</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif
