{{--
    The cart page: core's lines and subtotal, the summary with coupon,
    shipping estimate and checkout. Changing a quantity or removing a line
    posts to core's cart routes, then the section is drawn again from the
    real page so every flag and total is core's.
--}}
@php
    $d = $data;
    $toFree = $d['free_from'] !== null ? max(0, $d['free_from'] - $d['subtotal']) : null;
@endphp
<section class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs') aria-labelledby="{{ $section['id'] }}-title"
         data-cart-page-section data-endpoints='@json($d['endpoints'])'>
    <div class="s__inner" data-cart-page-body>
        <h1 class="listing-head__title cart-page__title" id="{{ $section['id'] }}-title" data-setting="heading">{{ $settings['heading'] }}</h1>
        @if ($d['lines'] === [])
            <div class="cart-empty cart-empty--page">
                <svg class="icon icon--xl" aria-hidden="true"><use href="#i-bag"></use></svg>
                <h2 class="cart-empty__title" data-setting="empty_text">{{ $settings['empty_text'] }}</h2>
                <a class="btn" href="{{ $d['empty_link'] }}" data-setting="empty_button">{{ $settings['empty_button'] }}</a>
            </div>
        @else
            <div class="cart-page" data-cart-page>
                <div class="cart-page__lines">
                    @if ($d['has_unavailable'])
                        <p class="cart-page__warning" role="alert">Some items are no longer available. Remove them before checking out.</p>
                    @endif
                    @if ($d['has_out_of_stock'])
                        <p class="cart-page__warning" role="alert">Some items are out of stock or short. Lower the quantity or remove them before checking out.</p>
                    @endif
                    <p class="cart-page__warning" data-cart-warning role="alert" hidden></p>
                    @if ($toFree !== null)
                        <div class="ship-note">
                            @if ($toFree <= 0)
                                <p class="ship-note__done"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-check"></use></svg>{{ \Themes\Xelga\Sections\ThemeValues::get('free_shipping_reached', 'Your order qualifies for free shipping.') }}</p>
                            @else
                                @php [$xelgaBefore, $xelgaAfter] = array_pad(explode('{amount}', (string) \Themes\Xelga\Sections\ThemeValues::get('free_shipping_message', 'Add {amount} more for free shipping.'), 2), 2, null); @endphp
                                <p>{{ $xelgaBefore }}@if ($xelgaAfter !== null)<strong>{{ money($toFree) }}</strong>{{ $xelgaAfter }}@endif</p>
                                <progress class="ship-meter" max="{{ $d['free_from'] }}" value="{{ $d['subtotal'] }}" aria-hidden="true"></progress>
                            @endif
                        </div>
                    @endif
                    @if ($d['groups'] === null)
                        <ul class="lines lines--page" aria-label="Items in your cart">
                            @foreach ($d['lines'] as $line)
                                @include('xelga::snippets.cart-line', ['line' => $line])
                            @endforeach
                        </ul>
                    @else
                        {{-- Lines an extension grouped (one group per seller, say); totals stay the cart's. --}}
                        @foreach ($d['groups'] as $group)
                            @include('xelga::snippets.cart-group', ['group' => $group, 'listClass' => 'lines lines--page', 'headingLevel' => 2])
                        @endforeach
                    @endif
                    <a class="link-arrow cart-page__back" href="{{ $d['empty_link'] }}"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-left"></use></svg><span>Continue shopping</span></a>
                </div>
                <aside class="summary scheme-2" aria-labelledby="{{ $section['id'] }}-summary">
                    <h2 class="summary__title" id="{{ $section['id'] }}-summary">Order summary</h2>
                    @if ($d['coupons_on'])
                        @if ($d['signed_in'])
                            @if ($d['coupon'])
                                <p class="coupon__applied">Coupon <strong>{{ $d['coupon']['code'] }}</strong> applied. <button type="button" class="totals__remove" data-coupon-remove>Remove coupon {{ $d['coupon']['code'] }}</button></p>
                            @else
                                <form class="coupon__form coupon__form--page" data-coupon-form novalidate>
                                    <label class="field__label" for="{{ $section['id'] }}-coupon">Coupon code</label>
                                    <div class="coupon__row">
                                        <input class="input" id="{{ $section['id'] }}-coupon" name="code" autocomplete="off" maxlength="50" aria-describedby="{{ $section['id'] }}-coupon-msg">
                                        <button class="btn btn--secondary" type="submit">Apply</button>
                                    </div>
                                    <p class="coupon__msg" id="{{ $section['id'] }}-coupon-msg" data-coupon-msg role="status"></p>
                                </form>
                            @endif
                        @elseif ($d['login'])
                            <p class="coupon__signin">Have a coupon? <a href="{{ $d['login'] }}">Sign in</a> to use it.</p>
                        @endif
                    @endif
                    <dl class="totals">
                        <dt>Subtotal</dt><dd>{{ money($d['subtotal']) }}</dd>
                        @if ($d['coupon'])
                            <dt>Coupon {{ $d['coupon']['code'] }}</dt><dd class="totals__discount">-{{ money($d['coupon']['discount']) }}</dd>
                            @if ($d['coupon']['free_shipping'])
                                <dt>Shipping</dt><dd class="totals__discount">Free with coupon</dd>
                            @endif
                        @endif
                    </dl>
                    @if ($d['estimate'])
                        <details class="estimate" data-estimate>
                            <summary class="estimate__summary">Estimate shipping<svg class="icon icon--sm" aria-hidden="true"><use href="#i-chevron-down"></use></svg></summary>
                            <form class="estimate__form" data-estimate-form novalidate>
                                <label class="field"><span class="field__label">Country</span>
                                    <select class="select" name="country_id" data-estimate-country required><option value="">Choose a country</option></select>
                                </label>
                                <div class="estimate__fields" data-estimate-fields></div>
                                <button class="btn btn--secondary btn--block" type="submit">Get estimate</button>
                                <div class="estimate__result" data-estimate-result role="status"></div>
                            </form>
                        </details>
                    @endif
                    <dl class="totals totals--grand">
                        <dt>Total</dt><dd>{{ money(max(0, $d['subtotal'] - (float) ($d['coupon']['discount'] ?? 0))) }}</dd>
                        <dt class="totals__hint">Shipping is added at checkout</dt><dd class="totals__hint"></dd>
                    </dl>
                    @if ($settings['show_cod_note'] && $d['cod'] && trim((string) $settings['cod_note']) !== '')
                        <p class="cart-note"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-cash"></use></svg><span data-setting="cod_note">{{ $settings['cod_note'] }}</span></p>
                    @endif
                    @if ($d['checkout'])
                        @if ($d['blocked'])
                            <button type="button" class="btn btn--lg btn--block" disabled>Check out</button>
                            <p class="cart-note">Fix the items marked above to check out.</p>
                        @else
                            <a class="btn btn--lg btn--block" href="{{ $d['checkout'] }}" data-checkout>Check out</a>
                            @if (! $d['signed_in'])
                                <p class="cart-note">You sign in or create an account before paying.</p>
                            @endif
                        @endif
                    @endif
                    @if ($d['payments'] !== [])
                        <ul class="pay-list summary__pay" aria-label="Payment methods">
                            @foreach ($d['payments'] as $payment)
                                <li class="pay">{{ $payment }}</li>
                            @endforeach
                        </ul>
                    @endif
                </aside>
            </div>
        @endif
    </div>
</section>
