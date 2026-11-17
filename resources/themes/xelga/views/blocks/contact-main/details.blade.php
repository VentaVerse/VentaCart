{{-- The store's email, phone and address from VentaCart, and the opening hours from the theme settings. --}}
@php
    $s = $block['settings'];
    $d = $data['details'];
    $shown = ($s['show_email'] && $d['email']) || ($s['show_phone'] && $d['phone']) || ($s['show_address'] && $d['address']) || ($s['show_hours'] && $d['hours']);
@endphp
@if ($shown || $context->preview)
    <div class="contact__details" data-block-id="{{ $block['id'] }}" data-block-type="details"
         data-show-email="{{ $s['show_email'] ? 'true' : 'false' }}" data-show-phone="{{ $s['show_phone'] ? 'true' : 'false' }}"
         data-show-address="{{ $s['show_address'] ? 'true' : 'false' }}" data-show-hours="{{ $s['show_hours'] ? 'true' : 'false' }}"
         @if ($block['disabled']) hidden @endif>
        <h2 class="contact__subtitle" data-setting="heading">{{ $s['heading'] !== '' ? $s['heading'] : $data['storeName'] }}</h2>
        @if (! $d['email'] && ! $d['phone'] && ! $d['address'] && ! $d['hours'])
            @include('xelga::snippets.editor-note', ['note' => 'The store has no email, phone or address yet. Add them in VentaCart, Settings, General.'])
        @else
            <dl class="store-details">
                @if ($d['email'])
                    <div data-detail="email"><dt><svg class="icon icon--sm" aria-hidden="true"><use href="#i-mail"></use></svg>Email</dt><dd><a href="mailto:{{ $d['email'] }}">{{ $d['email'] }}</a></dd></div>
                @endif
                @if ($d['phone'])
                    <div data-detail="phone"><dt><svg class="icon icon--sm" aria-hidden="true"><use href="#i-phone"></use></svg>Phone</dt><dd>@if ($d['tel'] !== '')<a href="tel:{{ $d['tel'] }}">{{ $d['phone'] }}</a>@else{{ $d['phone'] }}@endif</dd></div>
                @endif
                @if ($d['address'])
                    <div data-detail="address"><dt><svg class="icon icon--sm" aria-hidden="true"><use href="#i-map-pin"></use></svg>Address</dt><dd class="store-details__lines">{{ $d['address'] }}</dd></div>
                @endif
                @if ($d['hours'])
                    <div data-detail="hours"><dt><svg class="icon icon--sm" aria-hidden="true"><use href="#i-clock"></use></svg>Opening hours</dt><dd class="store-details__lines">{{ $d['hours'] }}</dd></div>
                @endif
            </dl>
        @endif
    </div>
@endif
