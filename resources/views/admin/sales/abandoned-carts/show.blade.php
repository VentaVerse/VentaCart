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
                        <x-admin.table.th>Price</x-admin.table.th>
                        <x-admin.table.th>Qty</x-admin.table.th>
                        <x-admin.table.th align="right">Line Total</x-admin.table.th>
                    </x-slot>
                    @foreach ($cart->items as $item)
                        <x-admin.table.row>
                            <x-admin.table.cell>
                                <div class="font-medium text-v-text">{{ $item->name }}</div>
                                @if ($item->sku)
                                    <div class="text-xs text-v-muted">SKU: {{ $item->sku }}</div>
                                @endif
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $item->variant_label ?? '—' }}</x-admin.table.cell>
                            <x-admin.table.cell mono class="text-v-text">{{ number_format($item->price, 2) }}</x-admin.table.cell>
                            <x-admin.table.cell mono class="text-v-text">{{ $item->quantity }}</x-admin.table.cell>
                            <x-admin.table.cell align="right" mono class="font-medium text-v-text">{{ number_format($item->line_total, 2) }}</x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach

                    <x-slot name="footer">
                        <div class="flex justify-end gap-6 text-[13px]">
                            <span class="font-medium text-v-text">Total</span>
                            <span class="v-num font-bold text-v-text">{{ number_format($cart->total, 2) }}</span>
                        </div>
                    </x-slot>
                </x-admin.table>
            </div>

            {{-- Customer Info --}}
            <div class="space-y-6">
                <x-admin.card title="Customer">
                    @if ($cart->customer)
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-xs text-v-muted">Name</dt>
                                <dd class="text-[13px] text-v-text">{{ $cart->customer->first_name }} {{ $cart->customer->last_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-v-muted">Email</dt>
                                <dd class="text-[13px] text-v-text">{{ $cart->customer->email }}</dd>
                            </div>
                            @if ($cart->customer->phone)
                                <div>
                                    <dt class="text-xs text-v-muted">Phone</dt>
                                    <dd class="text-[13px] text-v-text">{{ $cart->customer->phone }}</dd>
                                </div>
                            @endif
                        </dl>
                    @else
                        <p class="text-[13px] text-v-muted">Guest session</p>
                        <p class="mt-1 text-xs text-v-muted">Session: {{ $cart->session_id }}</p>
                    @endif
                </x-admin.card>

                <x-admin.card title="Timeline">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-v-muted">Created</dt>
                            <dd class="text-[13px] text-v-text">{{ $cart->created_at->format('M d, Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-v-muted">Last Activity</dt>
                            <dd class="text-[13px] text-v-text">{{ $cart->updated_at->format('M d, Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-v-muted">Abandoned For</dt>
                            <dd class="text-[13px] font-medium text-v-danger">{{ $cart->updated_at->diffForHumans() }}</dd>
                        </div>
                    </dl>
                </x-admin.card>

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
