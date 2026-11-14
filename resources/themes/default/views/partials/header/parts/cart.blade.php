            {{-- Cart --}}
            <div class="relative flex-shrink-0" x-data="miniCart({{ $cartCount }}, {{ $cartSubtotal }}, @js((string) $defaultCurrency?->symbol))" @mouseenter="onHover()" @mouseleave="onLeave()">
                <a href="{{ route('cart.index') }}" class="cart-btn" x-on:cart-updated.window="onCartUpdated($event.detail)">
                    <span class="hidden sm:inline font-mono text-xs cart-text" x-text="count + ' item(s) - ' + money(subtotal)">{{ $cartCount }} item(s) - {{ money($cartSubtotal) }}</span>
                    <div class="relative">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                        <span class="cart-badge absolute -top-1.5 -right-1.5 text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center" style="background: var(--color-primary); color: var(--color-btn-text); box-shadow: 0 0 8px var(--glow-primary);" x-text="count">{{ $cartCount }}</span>
                    </div>
                </a>

                {{-- Mini Cart Dropdown --}}
                <div x-show="open" x-transition.opacity.duration.200ms class="mini-cart-dropdown" @mouseenter="clearLeaveTimer()" @mouseleave="onLeave()">
                    <template x-if="loading">
                        <div class="mini-cart-loading">Loading...</div>
                    </template>
                    <template x-if="!loading && items.length === 0">
                        <div class="mini-cart-empty">Your cart is empty</div>
                    </template>
                    <template x-if="!loading && items.length > 0">
                        <div>
                            <div class="mini-cart-items">
                                <template x-for="(item, i) in items" :key="i">
                                    <div class="mini-cart-item">
                                        <div class="mini-cart-item-image">
                                            <template x-if="item.image">
                                                <img :src="storageUrl(item.image)" :alt="item.name" loading="lazy">
                                            </template>
                                            <template x-if="!item.image">
                                                <div class="mini-cart-item-noimage">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="mini-cart-item-info">
                                            <span class="mini-cart-item-name" x-text="item.name"></span>
                                            <span class="mini-cart-item-variant" x-show="item.variant_label" x-text="item.variant_label"></span>
                                        </div>
                                        <div class="mini-cart-item-qty" x-text="'x' + item.quantity"></div>
                                        <div class="mini-cart-item-total" x-text="symbol + item.line_total.toFixed(2)"></div>
                                        <button @click="removeItem(item.id, i)" class="mini-cart-item-remove" title="Remove">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <div class="mini-cart-footer">
                                <div class="mini-cart-subtotal">
                                    <span>Subtotal</span>
                                    <span x-text="symbol + cartSubtotal.toFixed(2)"></span>
                                </div>
                                <a href="{{ route('cart.index') }}" class="mini-cart-viewcart">VIEW CART</a>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
