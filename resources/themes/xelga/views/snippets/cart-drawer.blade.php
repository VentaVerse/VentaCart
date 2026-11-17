{{--
    The cart drawer, on every page: opened from the header's cart icon and
    after Add to cart. Its lines come from core's cart.mini answer and every
    change goes through core's cart routes; nothing is counted or priced
    here. A native dialog keeps focus inside and closes with Escape.
--}}
@php
    $xelgaOnCart = request()->routeIs('cart.index', 'checkout.*');
    $xelgaSummary = \Themes\Xelga\Sections\CartSummary::class;
    $xelgaSubtotal = (float) ($cartSubtotal ?? 0);
    $xelgaCoupon = $xelgaSummary::couponsOn() && $xelgaSummary::signedIn() ? $xelgaSummary::coupon($xelgaSubtotal) : null;
    $xelgaConfig = [
        'endpoints' => $xelgaSummary::endpoints(),
        'signed_in' => $xelgaSummary::signedIn(),
        'free_from' => $xelgaSummary::freeShippingFrom(),
        'free_message' => (string) \Themes\Xelga\Sections\ThemeValues::get('free_shipping_message', 'Add {amount} more for free shipping.'),
        'free_reached' => (string) \Themes\Xelga\Sections\ThemeValues::get('free_shipping_reached', 'Your order qualifies for free shipping.'),
        'coupon' => $xelgaCoupon,
        'storage' => '/storage/',
    ];
    $xelgaNote = trim((string) \Themes\Xelga\Sections\ThemeValues::get('drawer_note', ''));
@endphp
@if (! $xelgaOnCart && $xelgaConfig['endpoints']['mini'])
    <dialog class="drawer cart-drawer scheme-1" id="cart-drawer" aria-labelledby="cart-drawer-title" data-cart-drawer>
        <div class="drawer__head">
            <h2 class="cart-drawer__title" id="cart-drawer-title">Your cart <span class="cart-drawer__count" data-cart-count-text></span></h2>
            <button type="button" class="icon-btn" data-cart-close aria-label="Close cart"><svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg></button>
        </div>
        <div class="drawer__body cart-drawer__body">
            <p class="cart-drawer__loading muted" data-cart-loading>Loading your cart</p>
            <div class="ship-note" data-ship-note hidden></div>
            <p class="cart-drawer__warning" data-cart-warning role="alert" hidden></p>
            <ul class="lines" data-cart-lines aria-label="Items in your cart"></ul>
            <div class="cart-empty" data-cart-empty hidden>
                <svg class="icon icon--xl" aria-hidden="true"><use href="#i-bag"></use></svg>
                <p class="cart-empty__title">Your cart is empty</p>
                <p class="muted">What you add waits here while you keep shopping.</p>
                <a class="btn" href="{{ route('home') }}">Continue shopping</a>
            </div>
        </div>
        <div class="cart-drawer__foot" data-cart-foot hidden>
            @if ($xelgaSummary::couponsOn())
                <details class="coupon" data-coupon data-show-in-drawer>
                    <summary>Add a coupon code <svg class="icon icon--sm" aria-hidden="true"><use href="#i-chevron-down"></use></svg></summary>
                    @if ($xelgaSummary::signedIn())
                        <form class="coupon__form" data-coupon-form novalidate>
                            <label class="visually-hidden" for="drawer-coupon">Coupon code</label>
                            <input class="input" id="drawer-coupon" name="code" autocomplete="off" maxlength="50" placeholder="Coupon code" aria-describedby="drawer-coupon-msg">
                            <button class="btn btn--secondary" type="submit">Apply</button>
                            <p class="coupon__msg" id="drawer-coupon-msg" data-coupon-msg role="status"></p>
                        </form>
                    @else
                        <p class="coupon__signin"><a href="{{ $xelgaConfig['endpoints']['login'] }}">Sign in</a> to use a coupon code.</p>
                    @endif
                </details>
            @endif
            <dl class="totals" data-cart-totals></dl>
            @if ($xelgaNote !== '')
                <p class="cart-note"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-info"></use></svg><span>{{ $xelgaNote }}</span></p>
            @endif
            <div class="cart-drawer__actions">
                <a class="btn btn--secondary" href="{{ $xelgaConfig['endpoints']['cart'] }}">View cart</a>
                @if ($xelgaConfig['endpoints']['checkout'])
                    <a class="btn" href="{{ $xelgaConfig['endpoints']['checkout'] }}" data-checkout>Check out</a>
                @endif
            </div>
        </div>
        <script type="application/json" data-cart-config>@json($xelgaConfig)</script>
    </dialog>
@endif
<p class="visually-hidden" aria-live="polite" data-cart-live></p>
