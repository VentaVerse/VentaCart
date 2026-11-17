{{--
    Checkout, in core's two steps: the delivery address, then payment.
    Everything comes from core's checkout controller: saved addresses, cart
    lines, shipping quotes, payment methods for this customer, a coupon
    already on the cart, store credit. The page saves a new address through
    checkout.address.save (or account.addresses.update when editing),
    refreshes quotes through checkout.shipping-rates, changes lines through
    cart.update, applies coupons through the coupons extension, and places
    the order by posting core's own fields to checkout.place. The totals
    shown are for reading; core works out what is charged.
--}}
@php
    app(\Themes\Xelga\Rendering\Storefront::class)->page('checkout');
    $themeService = app(\App\Services\ThemeService::class);
    $msgStoreOn = $themeService->get('checkout.comment_store_enabled', '1') === '1';
    $msgCourierOn = $themeService->get('checkout.comment_courier_enabled', '0') === '1';
    $msgStoreLabel = trim((string) $themeService->get('checkout.comment_store_label', '')) ?: 'Message to the store';
    $msgCourierLabel = trim((string) $themeService->get('checkout.comment_courier_label', '')) ?: 'Message to the courier';
    $activeCurrency = $themeService->getActiveCurrency();
    $defaultCurrency = $themeService->getDefaultCurrency();
    $hasCoupons = \Illuminate\Support\Facades\Route::has('checkout.coupon.apply');
    $countryNames = $countries->pluck('name', 'id');
    $phoneCodes = $countries->pluck('phone_code', 'id');
    $methods = collect($paymentMethods)->map(fn ($m) => [
        'key' => (string) $m->getKey(),
        'name' => (string) $m->getName(),
        'description' => (string) $m->getDescription(),
        'image' => $m->getImage() ? (string) $m->getImage() : null,
    ])->values();
    $lineData = $items->map(fn ($item) => [
        'id' => (int) $item->id,
        'name' => (string) $item->name,
        'variant_label' => (string) ($item->variant_label ?? ''),
        'quantity' => (int) $item->quantity,
        'price' => (float) $item->price,
        'image' => $item->image ? storage_url($item->image) : null,
    ])->values();
    $addressData = $addresses->map(fn ($a) => [
        'id' => (int) $a->id,
        'first_name' => (string) $a->first_name, 'last_name' => (string) $a->last_name,
        'address_1' => (string) $a->address_1, 'city' => (string) $a->city,
        'district' => (string) ($a->district ?? ''), 'barangay' => (string) ($a->barangay ?? ''),
        'state' => (string) ($a->state ?? ''), 'postcode' => (string) ($a->postcode ?? ''),
        'country_id' => $a->country_id ? (int) $a->country_id : null, 'phone' => (string) ($a->phone ?? ''),
        'is_default' => (bool) $a->is_default,
    ])->values();
    $selectedAddressId = $defaultAddress?->id;
    $firstShipping = $shippingMethods[0]['key'] ?? null;
    $firstPayment = $methods->first()['key'] ?? null;
    $placeLine = function (array $a) use ($countryNames) {
        return collect([$a['address_1'], $a['barangay'], $a['district'], $a['city'], $a['state'], $a['postcode'], $countryNames[$a['country_id']] ?? null])->filter()->implode(', ');
    };
    $checkoutData = [
        'addresses' => $addressData,
        'selectedAddressId' => $selectedAddressId,
        'items' => $lineData,
        'subtotal' => (float) $subtotal,
        'shippingMethods' => array_values($shippingMethods),
        'paymentMethods' => $methods,
        'coupon' => ['code' => (string) ($couponCode ?? ''), 'discount' => (float) ($couponDiscount ?? 0), 'freeShipping' => (bool) ($couponFreeShipping ?? false)],
        'creditBalance' => (float) ($creditBalance ?? 0),
        'customer' => ['firstName' => (string) $customer->first_name, 'lastName' => (string) $customer->last_name],
        'countryNames' => (object) $countryNames->all(),
        'phoneCodes' => (object) $phoneCodes->filter()->all(),
        'routes' => [
            'shippingRates' => route('checkout.shipping-rates'),
            'saveAddress' => route('checkout.address.save'),
            'updateAddress' => route('account.addresses.update', ['address' => '__ID__']),
            'cartLine' => route('cart.update', ['cartItem' => '__ID__']),
            'cart' => route('cart.index'),
            'couponApply' => $hasCoupons ? route('checkout.coupon.apply') : null,
            'couponRemove' => $hasCoupons ? route('checkout.coupon.remove') : null,
        ],
    ];
    $phoneLine = function (array $a) use ($phoneCodes) {
        $code = $phoneCodes[$a['country_id']] ?? null;
        return $a['phone'] === '' ? '' : (($code ? '+' . $code . ' ' : '') . $a['phone']);
    };
@endphp
<x-store-layout title="Checkout">
    @include('xelga::partials.fixed.page-assets', ['checkout' => true])
    <div class="s fixed-page scheme-1" data-fixed-page="checkout" data-checkout>
        <script type="application/json" data-checkout-data>@json($checkoutData)</script>
        <div class="s__inner">
            <header class="co-head">
                <h1 class="co-title">Checkout</h1>
                <ol class="steps-bar" aria-label="Checkout steps">
                    <li><button type="button" class="steps-bar__step" data-go-step="1" aria-current="step"><span class="steps-bar__num">1</span><span>Delivery address</span></button></li>
                    <li aria-hidden="true" class="steps-bar__line"></li>
                    <li><button type="button" class="steps-bar__step" data-go-step="2" disabled><span class="steps-bar__num">2</span><span>Payment</span></button></li>
                </ol>
                <p class="co-signed muted"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-lock"></use></svg>Signed in as {{ $customer->first_name }} {{ $customer->last_name }}</p>
            </header>
            @include('xelga::partials.fixed.flash', ['showErrors' => true])
            <noscript><div class="form-status form-status--error" role="alert"><span>Checkout needs JavaScript switched on in your browser.</span></div></noscript>
            <div class="co">
                <div class="co__main">
                    <section class="co__step" data-step="1" aria-labelledby="co-address-title">
                        <h2 class="co__subtitle" id="co-address-title" tabindex="-1">Where should we deliver?</h2>
                        <fieldset class="choices" data-address-list @if ($addressData->isEmpty()) hidden @endif>
                            <legend class="visually-hidden">Saved addresses</legend>
                            @foreach ($addressData as $a)
                                <div class="choice-row" data-address-row="{{ $a['id'] }}">
                                    <label class="choice">
                                        <input type="radio" name="checkout_address" value="{{ $a['id'] }}" @checked($a['id'] === $selectedAddressId)>
                                        <span class="choice__body">
                                            <strong>{{ $a['first_name'] }} {{ $a['last_name'] }}</strong>
                                            @if ($a['is_default'])<span class="pill">Default</span>@endif
                                            <span class="muted">{{ $placeLine($a) }}</span>
                                            @if ($phoneLine($a) !== '')<span class="muted tabular">{{ $phoneLine($a) }}</span>@endif
                                        </span>
                                    </label>
                                    <button type="button" class="text-btn choice-row__edit" data-edit-address="{{ $a['id'] }}" aria-label="Edit the address of {{ $a['first_name'] }} {{ $a['last_name'] }}">Edit</button>
                                </div>
                            @endforeach
                        </fieldset>
                        <button type="button" class="text-btn" data-new-address @if ($addressData->isEmpty()) hidden @endif><svg class="icon icon--sm" aria-hidden="true"><use href="#i-plus"></use></svg>Deliver to a new address</button>

                        <div class="co__new" data-address-panel @if ($addressData->isNotEmpty()) hidden @endif>
                            <h3 class="co__subtitle co__subtitle--sm" data-address-panel-title tabindex="-1">New address</h3>
                            <div class="form-grid" data-address-form-fields>
                                <div class="field">
                                    <label class="field__label" for="na-first">First name</label>
                                    <input class="input" id="na-first" data-addr-value="first_name" value="{{ $customer->first_name }}" autocomplete="given-name" maxlength="255" required>
                                </div>
                                <div class="field">
                                    <label class="field__label" for="na-last">Last name</label>
                                    <input class="input" id="na-last" data-addr-value="last_name" value="{{ $customer->last_name }}" autocomplete="family-name" maxlength="255" required>
                                </div>
                                @include('xelga::partials.fixed.address-fields', ['prefix' => 'na', 'named' => false, 'values' => [], 'countries' => $countries])
                                <div class="field field--full">
                                    <label class="field__label" for="na-line">House number, street and building</label>
                                    <input class="input" id="na-line" data-addr-value="address_1" autocomplete="address-line1" maxlength="1000" required>
                                </div>
                                <div class="field">
                                    <label class="field__label" for="na-zip">Postal code <span class="muted">(optional)</span></label>
                                    <input class="input tabular" id="na-zip" data-addr-value="postcode" autocomplete="postal-code" maxlength="20">
                                </div>
                                <div class="field">
                                    <label class="field__label" for="na-phone">Mobile number</label>
                                    <div class="phone">
                                        <span class="phone__code" data-phone-code aria-hidden="true"></span>
                                        <input class="input tabular" id="na-phone" type="tel" data-addr-value="phone" autocomplete="tel-national" inputmode="numeric" minlength="7" maxlength="13" required data-digits-only>
                                    </div>
                                </div>
                            </div>
                            <div class="form-status form-status--error" role="alert" data-address-errors hidden></div>
                            <div class="co__new-acts">
                                <button type="button" class="btn" data-save-address>Save this address</button>
                                <button type="button" class="btn btn--secondary" data-cancel-address @if ($addressData->isEmpty()) hidden @endif>Cancel</button>
                                <a class="btn btn--secondary" href="{{ route('cart.index') }}" data-back-to-cart @if ($addressData->isNotEmpty()) hidden @endif>Back to the cart</a>
                            </div>
                        </div>

                        <label class="check" data-billing-toggle-row><input type="checkbox" data-different-billing><span>Use a different billing address</span></label>
                        <div class="co__billing" data-billing hidden>
                            <h3 class="co__subtitle co__subtitle--sm">Billing address</h3>
                            <div class="form-grid">
                                <div class="field">
                                    <label class="field__label" for="b-first">First name</label>
                                    <input class="input" id="b-first" data-billing-value="first_name" value="{{ $customer->first_name }}" autocomplete="billing given-name" maxlength="255">
                                </div>
                                <div class="field">
                                    <label class="field__label" for="b-last">Last name</label>
                                    <input class="input" id="b-last" data-billing-value="last_name" value="{{ $customer->last_name }}" autocomplete="billing family-name" maxlength="255">
                                </div>
                                <div class="field field--full">
                                    <label class="field__label" for="b-line">House number, street and building</label>
                                    <input class="input" id="b-line" data-billing-value="address_1" autocomplete="billing address-line1" maxlength="1000">
                                </div>
                                @include('xelga::partials.fixed.address-fields', ['prefix' => 'b', 'named' => false, 'values' => [], 'countries' => $countries])
                                <div class="field">
                                    <label class="field__label" for="b-zip">Postal code <span class="muted">(optional)</span></label>
                                    <input class="input tabular" id="b-zip" data-billing-value="postcode" autocomplete="billing postal-code" maxlength="20">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="co__step" data-step="2" aria-labelledby="co-pay-title" hidden>
                        <h2 class="co__subtitle" id="co-pay-title" tabindex="-1">How would you like to pay?</h2>
                        @if ($methods->isEmpty())
                            <div class="form-status form-status--error" role="alert"><span>No payment method is available for this order. Contact the store for help.</span></div>
                        @else
                            <fieldset class="choices">
                                <legend class="visually-hidden">Payment method</legend>
                                @foreach ($methods as $method)
                                    <label class="choice">
                                        <input type="radio" name="checkout_payment" value="{{ $method['key'] }}" @checked($method['key'] === $firstPayment)>
                                        <span class="choice__body">
                                            <strong>{{ $method['name'] }}</strong>
                                            @if ($method['description'] !== '')<span class="muted">{{ $method['description'] }}</span>@endif
                                        </span>
                                    </label>
                                    @if ($method['image'])
                                        <div class="choice__qr" data-payment-qr="{{ $method['key'] }}" @if ($method['key'] !== $firstPayment) hidden @endif>
                                            <button type="button" class="choice__qr-btn" data-qr-open data-qr-src="{{ $method['image'] }}" data-qr-name="{{ $method['name'] }}" aria-label="Show the {{ $method['name'] }} QR code larger">
                                                <img src="{{ $method['image'] }}" alt="QR code for {{ $method['name'] }}" width="192" height="192" loading="lazy">
                                            </button>
                                            <p class="muted">Tap to enlarge</p>
                                        </div>
                                    @endif
                                @endforeach
                            </fieldset>
                        @endif
                        @if ($msgStoreOn || $msgCourierOn)
                            <div class="form-grid">
                                @if ($msgStoreOn)
                                    <div class="field field--full">
                                        <label class="field__label" for="n-shop">{{ $msgStoreLabel }} <span class="muted">(optional)</span></label>
                                        <textarea class="input textarea" id="n-shop" rows="3" maxlength="2000" data-order-value="comment" placeholder="Notes for the team packing your order"></textarea>
                                    </div>
                                @endif
                                @if ($msgCourierOn)
                                    <div class="field field--full">
                                        <label class="field__label" for="n-courier">{{ $msgCourierLabel }} <span class="muted">(optional)</span></label>
                                        <textarea class="input textarea" id="n-courier" rows="3" maxlength="2000" data-order-value="courier_comment" placeholder="Landmarks, gate codes, when someone is home"></textarea>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </section>
                </div>

                <aside class="co__summary" aria-labelledby="co-summary-title">
                    <h2 class="co__subtitle" id="co-summary-title">Order summary</h2>
                    <p class="form-status form-status--error" role="alert" data-stock-warning hidden></p>
                    <ul class="lines co-lines" data-lines>
                        @foreach ($lineData as $line)
                            <li class="line line--sm" data-line="{{ $line['id'] }}">
                                <span class="line__img">
                                    @if ($line['image'])<img src="{{ $line['image'] }}" alt="" width="56" height="70" loading="lazy">@else<span class="line__ph" aria-hidden="true"></span>@endif
                                    <span class="line__qty" aria-hidden="true" data-line-badge>{{ $line['quantity'] }}</span>
                                </span>
                                <div class="line__text">
                                    <span>{{ $line['name'] }}</span>
                                    @if ($line['variant_label'] !== '')<span class="muted">{{ $line['variant_label'] }}</span>@endif
                                    <div class="co-qty">
                                        <div class="qty qty--sm">
                                            <button type="button" class="qty__btn" data-line-step="-1" aria-label="One fewer {{ $line['name'] }}"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-minus"></use></svg></button>
                                            <label class="visually-hidden" for="co-qty-{{ $line['id'] }}">Quantity of {{ $line['name'] }}</label>
                                            <input class="qty__input" id="co-qty-{{ $line['id'] }}" type="number" inputmode="numeric" min="1" value="{{ $line['quantity'] }}" data-line-qty>
                                            <button type="button" class="qty__btn" data-line-step="1" aria-label="One more {{ $line['name'] }}"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-plus"></use></svg></button>
                                        </div>
                                        <button type="button" class="text-btn co-qty__remove" data-line-remove>Remove</button>
                                    </div>
                                </div>
                                <span class="line__price tabular" data-line-total>{{ money($line['price'] * $line['quantity']) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <fieldset class="ship" data-shipping aria-live="polite">
                        <legend class="ship__legend">Delivery</legend>
                        <div data-shipping-list>
                            @forelse ($shippingMethods as $sm)
                                <label class="ship__opt">
                                    <input type="radio" name="checkout_shipping" value="{{ $sm['key'] }}" @checked($sm['key'] === $firstShipping)>
                                    <span><strong>{{ $sm['name'] }}</strong>@if (! empty($sm['estimated_days']))<span class="muted">About {{ $sm['estimated_days'] }} days</span>@endif</span>
                                    <span class="tabular">{{ (float) $sm['cost'] === 0.0 ? 'Free' : money($sm['cost']) }}</span>
                                </label>
                            @empty
                                <p class="muted ship__empty">{{ $addressData->isEmpty() ? 'Add your delivery address to see delivery options.' : 'No delivery option reaches this address. Choose another address, or contact the store.' }}</p>
                            @endforelse
                        </div>
                    </fieldset>

                    @if ($hasCoupons)
                        <div class="coupon-box" data-coupon-box>
                            @if (! empty($couponError))
                                <p class="field__error">{{ $couponError }}</p>
                            @endif
                            <form class="coupon" data-coupon-form novalidate @if (! empty($couponCode)) hidden @endif>
                                <label class="visually-hidden" for="co-coupon">Coupon code</label>
                                <input class="input" id="co-coupon" name="code" maxlength="50" placeholder="Coupon code" autocomplete="off" aria-describedby="co-coupon-msg">
                                <button type="submit" class="btn btn--secondary">Apply</button>
                                <p class="field__error coupon__msg" id="co-coupon-msg" data-coupon-msg role="status" hidden></p>
                            </form>
                            <p class="coupon-applied" data-coupon-applied @if (empty($couponCode)) hidden @endif>
                                <span>Coupon <strong data-coupon-code>{{ $couponCode }}</strong> <span class="muted" data-coupon-free @if (empty($couponFreeShipping)) hidden @endif>with free delivery</span></span>
                                <button type="button" class="text-btn" data-coupon-remove>Remove</button>
                            </p>
                        </div>
                    @endif

                    <dl class="totals">
                        <div><dt>Subtotal</dt><dd class="tabular" data-total-subtotal>{{ money($subtotal) }}</dd></div>
                        <div><dt>Delivery</dt><dd class="tabular" data-total-shipping></dd></div>
                        <div data-total-coupon-row hidden><dt>Coupon</dt><dd class="tabular" data-total-coupon></dd></div>
                        <div data-total-credit-row hidden><dt>Store credit</dt><dd class="tabular" data-total-credit></dd></div>
                        <div class="totals__grand"><dt>Total</dt><dd class="tabular" data-total-grand>{{ money($subtotal) }}</dd></div>
                    </dl>
                    @if ($activeCurrency && ! $activeCurrency->is_default && $defaultCurrency)
                        <p class="muted co__fine" data-currency-note>Prices shown in {{ $activeCurrency->code }}. Your order is charged in {{ $defaultCurrency->code }}.</p>
                    @endif
                    <div class="co__actions">
                        <button type="button" class="btn btn--block btn--lg" data-next-step @disabled(! $selectedAddressId)>Continue to payment</button>
                        <button type="button" class="btn btn--block btn--lg" data-place-order hidden>Place order</button>
                        <button type="button" class="text-btn" data-back-step hidden><svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-left"></use></svg>Back to delivery address</button>
                        <a class="text-btn" href="{{ route('cart.index') }}" data-cart-link>Back to the cart</a>
                    </div>
                </aside>
            </div>

            {{-- The order as core receives it. Only the shipping method's key is sent: core prices it from its own quote, never from a cost the page posts. --}}
            <form method="POST" action="{{ route('checkout.place') }}" data-order-form hidden>
                @csrf
                <input type="hidden" name="shipping_address_id" value="{{ $selectedAddressId }}">
                <input type="hidden" name="payment_method" value="{{ $firstPayment }}">
                <input type="hidden" name="shipping_method" value="{{ $firstShipping }}">
                <input type="hidden" name="comment" value="">
                <input type="hidden" name="courier_comment" value="">
                <input type="hidden" name="different_billing" value="0">
                <input type="hidden" name="billing_first_name" value="">
                <input type="hidden" name="billing_last_name" value="">
                <input type="hidden" name="billing_address_1" value="">
                <input type="hidden" name="billing_city" value="">
                <input type="hidden" name="billing_district" value="">
                <input type="hidden" name="billing_barangay" value="">
                <input type="hidden" name="billing_state" value="">
                <input type="hidden" name="billing_postcode" value="">
                <input type="hidden" name="billing_country_id" value="">
            </form>

            <dialog class="co-dialog scheme-1" data-confirm aria-labelledby="co-confirm-title">
                <div class="co-dialog__inner">
                    <h2 class="co__subtitle" id="co-confirm-title">Confirm your order</h2>
                    <div class="co-dialog__body" data-confirm-body></div>
                    <div class="co-dialog__acts">
                        <button type="button" class="btn btn--secondary" data-confirm-back>Go back</button>
                        <button type="button" class="btn" data-confirm-place>Place my order</button>
                    </div>
                </div>
            </dialog>
            <dialog class="co-dialog co-dialog--qr scheme-1" data-qr aria-labelledby="co-qr-title">
                <div class="co-dialog__inner">
                    <h2 class="co__subtitle" id="co-qr-title" data-qr-title>QR code</h2>
                    <img src="" alt="" data-qr-img>
                    <p class="muted">Scan with your banking app.</p>
                    <button type="button" class="btn btn--secondary btn--block" data-qr-close>Close</button>
                </div>
            </dialog>
            @include('xelga::partials.fixed.address-data')
        </div>
    </div>
</x-store-layout>
