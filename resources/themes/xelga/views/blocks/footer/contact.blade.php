{{-- The store's contact details, from the store record in VentaCart admin. --}}
@php
    $s = $block['settings'];
    $c = $data['contact'];
    $phone = $s['show_phone'] ? $c['phone'] : null;
    $email = $s['show_email'] ? $c['email'] : null;
    $address = $s['show_address'] ? $c['address'] : null;
    $hours = ($s['show_hours'] ?? true) ? $c['hours'] : null;
    $tel = $phone ? preg_replace('/[^0-9+]/', '', $phone) : '';
@endphp
@if ($phone || $email || $address || $hours || $context->preview)
    <div class="footer__col footer__col--contact" data-block-id="{{ $block['id'] }}" data-block-type="contact"
         @if ($block['disabled']) hidden @endif>
        <h2 class="footer__heading" data-setting="heading">{{ $s['heading'] }}</h2>
        <ul class="footer__links footer__contact">
            @if ($phone)
                <li><svg class="icon icon--sm" aria-hidden="true"><use href="#i-phone"></use></svg>@if ($tel !== '')<a href="tel:{{ $tel }}" data-contact="phone">{{ $phone }}</a>@else<span data-contact="phone">{{ $phone }}</span>@endif</li>
            @endif
            @if ($email)
                <li><svg class="icon icon--sm" aria-hidden="true"><use href="#i-mail"></use></svg><a href="mailto:{{ $email }}" data-contact="email">{{ $email }}</a></li>
            @endif
            @if ($address)
                <li><svg class="icon icon--sm" aria-hidden="true"><use href="#i-map-pin"></use></svg><span data-contact="address">{{ $address }}</span></li>
            @endif
            @if ($hours)
                <li><svg class="icon icon--sm" aria-hidden="true"><use href="#i-clock"></use></svg><span data-contact="hours">{{ $hours }}</span></li>
            @endif
        </ul>
    </div>
@endif
