<x-admin.shell title="Abandoned Cart #{{ $cart->id }}">
    <x-admin.page title="Cart #{{ $cart->id }}">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.abandoned-carts.index') }}">Back to Abandoned Carts</x-admin.button>
        </x-slot:actions>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Cart Details --}}
            <div class="lg:col-span-2">
                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Product</x-admin.table.th>
                        <x-admin.table.th>Variant</x-admin.table.th>
                        <x-admin.table.th align="right">Price</x-admin.table.th>
                        <x-admin.table.th align="right">Qty</x-admin.table.th>
                        <x-admin.table.th align="right">Line Total</x-admin.table.th>
                    </x-slot>
                    @foreach ($cart->items as $item)
                        <x-admin.table.row>
                            <x-admin.table.cell>
                                <span class="v-lead">{{ $item->name }}</span>
                                @if ($item->sku)
                                    <span class="v-sub">SKU: {{ $item->sku }}</span>
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $item->variant_label ?? '-' }}</x-admin.table.cell>
                            <x-admin.table.cell align="right"><x-admin.money mark="₱" :amount="number_format($item->price, 2)" /></x-admin.table.cell>
                            <x-admin.table.cell align="right" mono class="text-v-text">{{ $item->quantity }}</x-admin.table.cell>
                            <x-admin.table.cell align="right"><x-admin.money mark="₱" :amount="number_format($item->line_total, 2)" /></x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach

                    <x-slot name="footer">
                        <div class="flex justify-end gap-6 text-[13px]">
                            <span class="font-medium text-v-text">Total</span>
                            <x-admin.money mark="₱" :amount="number_format($cart->total, 2)" />
                        </div>
                    </x-slot>
                </x-admin.table>
            </div>

            {{-- Customer Info --}}
            <div class="space-y-6">
                <x-admin.panel title="Customer" id="cart-customer" flush>
                    @if ($cart->customer)
                        <dl class="v-facts">
                            <dt class="v-label">Name</dt>
                            <dd>{{ $cart->customer->first_name }} {{ $cart->customer->last_name }}</dd>
                            <dt class="v-label">Email</dt>
                            <dd>{{ $cart->customer->email }}</dd>
                            @if ($cart->customer->phone)
                                <dt class="v-label">Phone</dt>
                                <dd>{{ $cart->customer->phone }}</dd>
                            @endif
                        </dl>
                    @else
                        <p class="v-panel__blank">Guest session</p>
                        <dl class="v-facts">
                            <dt class="v-label">Session</dt>
                            <dd><span class="v-sub">{{ $cart->session_id }}</span></dd>
                        </dl>
                    @endif
                </x-admin.panel>

                <x-admin.panel title="Timeline" id="cart-timeline" flush>
                    <dl class="v-facts">
                        <dt class="v-label">Created</dt>
                        <dd>{{ $cart->created_at->format('M d, Y H:i') }}</dd>
                        <dt class="v-label">Last Activity</dt>
                        <dd>{{ $cart->updated_at->format('M d, Y H:i') }}</dd>
                        <dt class="v-label">Abandoned For</dt>
                        <dd>{{ $cart->updated_at->diffForHumans() }}</dd>
                    </dl>
                </x-admin.panel>

                <x-admin.button variant="danger" class="w-full" type="button"
                    @click="$dispatch('open-modal', 'delete-cart')">
                    Delete Cart
                </x-admin.button>
                <x-admin.confirm-dialog name="delete-cart" title="Delete abandoned cart"
                    message="Delete this abandoned cart? This cannot be undone." confirm="Delete Cart"
                    :action="route('admin.abandoned-carts.destroy', $cart)" method="DELETE" />
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
