<x-admin.settings-shell title="Order Statuses" :wide="true">
        <div class="space-y-5">
            <x-admin.card title="Stock actions" description="What happens to inventory when an order enters a status.">
                <div class="flex flex-wrap gap-4 text-[13px]">
                    <span class="flex items-center gap-2"><x-admin.badge tone="danger">▼ Decrements</x-admin.badge><span class="text-v-secondary">stock committed (e.g. Processing)</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="success">▲ Increments</x-admin.badge><span class="text-v-secondary">stock returned (e.g. Refunded, Canceled)</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="neutral">-</x-admin.badge><span class="text-v-secondary">no inventory effect</span></span>
                </div>
            </x-admin.card>

            <x-admin.card title="Fulfillment placements" description="Which fulfillment queue tab a status belongs to.">
                <div class="flex flex-wrap gap-4 text-[13px]">
                    <span class="flex items-center gap-2"><x-admin.badge tone="warning">Unpaid</x-admin.badge><span class="text-v-secondary">waiting on payment, appears in the Unpaid tab</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="success">To Pack</x-admin.badge><span class="text-v-secondary">paid and awaiting packing, appears in To Ship &gt; To Pack</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="success">To Handover</x-admin.badge><span class="text-v-secondary">packed and awaiting courier pickup, appears in To Ship &gt; To Handover</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="success">In Transit</x-admin.badge><span class="text-v-secondary">with the courier en route, appears in the In Transit tab</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="success">Delivered</x-admin.badge><span class="text-v-secondary">delivered to the customer, appears in the Delivered tab</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="danger">Failed</x-admin.badge><span class="text-v-secondary">delivery failed or is returning, appears in the Failed tab</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="danger">Cancelled</x-admin.badge><span class="text-v-secondary">will not be fulfilled, appears in the Cancelled tab</span></span>
                </div>
            </x-admin.card>

            <x-admin.card title="Statuses">
                {{--
                    A <form> cannot legally wrap table rows/cells (invalid HTML: form is not a
                    permitted child of <tr>). Each row's edit form is instead declared as an
                    empty, id'd <form> and every input/select/checkbox/button inside the row
                    references it via the HTML5 form="" attribute — keeps the table structure
                    valid (and column widths governed by one single table) while each row still
                    submits independently. Pattern matches sections/currencies.blade.php.
                --}}
                @foreach ($statuses as $status)
                    <form id="status-update-{{ $status->id }}" method="POST" action="{{ route('admin.order-statuses.update', $status) }}" data-v-form class="hidden">
                        @csrf @method('PUT')
                    </form>
                @endforeach

                @php
                    // Label + badge tone per placement, kept next to the table
                    // that renders them so the two never drift apart.
                    $placementMeta = [
                        'unpaid'      => ['label' => 'Unpaid', 'tone' => 'warning'],
                        'to_pack'     => ['label' => 'To Pack', 'tone' => 'success'],
                        'to_handover' => ['label' => 'To Handover', 'tone' => 'success'],
                        'in_transit'  => ['label' => 'In Transit', 'tone' => 'success'],
                        'delivered'   => ['label' => 'Delivered', 'tone' => 'success'],
                        'failed'      => ['label' => 'Failed', 'tone' => 'danger'],
                        'cancelled'   => ['label' => 'Cancelled', 'tone' => 'danger'],
                    ];
                @endphp

                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th>Stock action</x-admin.table.th>
                        <x-admin.table.th>Fulfillment placement</x-admin.table.th>
                        <x-admin.table.th>Orders</x-admin.table.th>
                        <x-admin.table.th>Sort</x-admin.table.th>
                        <x-admin.table.th align="right">Actions</x-admin.table.th>
                    </x-slot:head>
                    @foreach ($statuses as $status)
                        @php $editForm = 'status-update-'.$status->id; @endphp
                        <x-admin.table.row>
                            <x-admin.table.cell>
                                <div class="flex items-center gap-2">
                                    <input type="text" form="{{ $editForm }}" name="name" value="{{ old('name', $status->name) }}" required maxlength="255"
                                        class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                    @if ($status->is_default)
                                        <x-admin.badge tone="success">Default</x-admin.badge>
                                    @endif
                                </div>
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                <div class="flex items-center gap-2">
                                    <select form="{{ $editForm }}" name="stock_action" class="v-control h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        <option value="" @selected(! $status->stock_action)>None</option>
                                        <option value="decrement" @selected($status->stock_action === 'decrement')>Decrement</option>
                                        <option value="increment" @selected($status->stock_action === 'increment')>Increment</option>
                                    </select>
                                    @if ($status->stock_action === 'decrement')
                                        <x-admin.badge tone="danger">▼ Decrements</x-admin.badge>
                                    @elseif ($status->stock_action === 'increment')
                                        <x-admin.badge tone="success">▲ Increments</x-admin.badge>
                                    @else
                                        <x-admin.badge tone="neutral">-</x-admin.badge>
                                    @endif
                                </div>
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                <div class="flex items-center gap-2">
                                    <select form="{{ $editForm }}" name="fulfillment_placement" class="v-control h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                        @foreach ($placementMeta as $value => $meta)
                                            <option value="{{ $value }}" @selected($status->fulfillment_placement === $value)>{{ $meta['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <x-admin.badge :tone="$placementMeta[$status->fulfillment_placement]['tone'] ?? 'success'">
                                        {{ $placementMeta[$status->fulfillment_placement]['label'] ?? $status->fulfillment_placement }}
                                    </x-admin.badge>
                                </div>
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                <span class="text-v-secondary">{{ $orderCounts[$status->id] ?? 0 }}</span>
                            </x-admin.table.cell>
                            <x-admin.table.cell>
                                <input type="number" form="{{ $editForm }}" name="sort_order" value="{{ old('sort_order', $status->sort_order) }}" min="0"
                                    class="v-control w-20 h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                            </x-admin.table.cell>
                            <x-admin.table.cell align="right">
                                <div class="flex items-center justify-end gap-3">
                                    <label class="inline-flex items-center gap-1.5 text-[13px] text-v-text cursor-pointer">
                                        <input type="hidden" form="{{ $editForm }}" name="is_default" value="0">
                                        <input type="checkbox" form="{{ $editForm }}" name="is_default" value="1" @checked(old('is_default', $status->is_default))
                                            class="w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                        <span>Default</span>
                                    </label>
                                    <x-admin.button variant="secondary" size="sm" type="submit" form="{{ $editForm }}">Save</x-admin.button>
                                    @unless ($status->is_default)
                                        <x-admin.button variant="ghost" size="sm" type="button" class="text-v-danger hover:text-v-danger" @click="$dispatch('open-modal', 'delete-status-{{ $status->id }}')">Delete</x-admin.button>
                                    @endunless
                                </div>
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            </x-admin.card>

            @foreach ($statuses as $status)
                @unless ($status->is_default)
                    <x-admin.confirm-dialog name="delete-status-{{ $status->id }}" title="Delete “{{ $status->name }}”?"
                        message="Orders using this status keep their history; the status becomes unavailable for new changes."
                        confirm="Delete" :action="route('admin.order-statuses.destroy', $status)" method="DELETE" />
                @endunless
            @endforeach

            <x-admin.card title="Add status">
                <form method="POST" action="{{ route('admin.order-statuses.store') }}" data-v-form class="grid grid-cols-1 gap-4 sm:grid-cols-5 sm:items-end">
                    @csrf
                    <x-admin.field name="name" label="Name">
                        <x-admin.input name="name" required placeholder="Quality Check" />
                    </x-admin.field>
                    <x-admin.field name="stock_action" label="Stock action">
                        <x-admin.select name="stock_action">
                            <option value="">None</option>
                            <option value="decrement">Decrement</option>
                            <option value="increment">Increment</option>
                        </x-admin.select>
                    </x-admin.field>
                    <x-admin.field name="fulfillment_placement" label="Fulfillment placement">
                        <x-admin.select name="fulfillment_placement">
                            <option value="unpaid">Unpaid</option>
                            <option value="to_pack" selected>To Pack</option>
                            <option value="to_handover">To Handover</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </x-admin.select>
                    </x-admin.field>
                    <x-admin.field name="sort_order" label="Sort">
                        <x-admin.input name="sort_order" type="number" min="0" value="0" />
                    </x-admin.field>
                    <div class="flex items-center gap-3">
                        <x-admin.checkbox name="is_default" label="Default" />
                        <x-admin.button variant="primary" type="submit">Add</x-admin.button>
                    </div>
                </form>
            </x-admin.card>
        </div>
</x-admin.settings-shell>
