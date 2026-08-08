<x-admin.settings-shell title="Shipping Couriers" :wide="true">
        <x-admin.card>
            <p class="text-xs text-v-secondary mb-4">Manual couriers for tracking shipments. Use <code class="bg-v-sunken px-1 rounded text-[11px]">{tracking_number}</code> in the URL template where the tracking code should be inserted.</p>

            <div x-data="{ editing: null }">
                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th>Courier</x-admin.table.th>
                        <x-admin.table.th>Tracking URL Template</x-admin.table.th>
                        <x-admin.table.th align="right">Order</x-admin.table.th>
                        <x-admin.table.th align="right">Active</x-admin.table.th>
                        <x-admin.table.th align="right">Actions</x-admin.table.th>
                    </x-slot:head>
                    @forelse ($couriers as $courier)
                        <tr class="v-row">
                            {{-- Read mode --}}
                            <template x-if="editing !== {{ $courier->id }}">
                                <td class="px-4 py-2 align-middle text-[13px] font-medium text-v-text">{{ $courier->name }}</td>
                            </template>
                            <template x-if="editing !== {{ $courier->id }}">
                                <td class="px-4 py-2 align-middle text-xs text-v-secondary font-mono truncate max-w-xs" title="{{ $courier->tracking_url }}">{{ $courier->tracking_url ?? '-' }}</td>
                            </template>
                            <template x-if="editing !== {{ $courier->id }}">
                                <td class="px-4 py-2 align-middle text-right text-[13px] text-v-secondary v-num">{{ $courier->sort_order }}</td>
                            </template>
                            <template x-if="editing !== {{ $courier->id }}">
                                <td class="px-4 py-2 align-middle text-right">
                                    <x-admin.badge :tone="$courier->is_active ? 'success' : 'neutral'">{{ $courier->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                                </td>
                            </template>
                            <template x-if="editing !== {{ $courier->id }}">
                                <td class="px-4 py-2 align-middle text-right text-[13px]">
                                    <button type="button" @click="editing = {{ $courier->id }}" class="text-v-accent hover:opacity-80 mr-3">Edit</button>
                                    <button type="button" class="text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-courier-{{ $courier->id }}')">Delete</button>
                                    <x-admin.confirm-dialog name="delete-courier-{{ $courier->id }}" title="Delete courier"
                                        message="Delete {{ $courier->name }}? This cannot be undone." confirm="Delete"
                                        :action="route('admin.shipping-couriers.destroy', $courier)" method="DELETE" />
                                </td>
                            </template>

                            {{-- Edit mode --}}
                            <template x-if="editing === {{ $courier->id }}">
                                <td colspan="5" class="px-4 py-3 align-middle">
                                    <form method="POST" action="{{ route('admin.shipping-couriers.update', $courier) }}" data-v-form class="space-y-3">
                                        @csrf @method('PUT')
                                        <div class="flex items-center gap-3">
                                            <input type="text" name="name" value="{{ $courier->name }}" required placeholder="Courier name"
                                                class="v-control w-40 h-[var(--control-h-sm)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            <input type="number" name="sort_order" value="{{ $courier->sort_order }}" min="0"
                                                class="v-control w-16 h-[var(--control-h-sm)] px-2.5 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            <x-admin.checkbox name="is_active" :checked="$courier->is_active" label="Active" />
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <input type="text" name="tracking_url" value="{{ $courier->tracking_url }}" placeholder="https://example.com/track/{tracking_number}"
                                                class="v-control flex-1 h-[var(--control-h-sm)] px-2.5 text-xs font-mono bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            <x-admin.button type="submit" size="sm">Save</x-admin.button>
                                            <x-admin.button variant="secondary" size="sm" type="button" @click="editing = null">Cancel</x-admin.button>
                                        </div>
                                    </form>
                                </td>
                            </template>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-2"><x-admin.empty-state icon="fulfillment" title="No couriers configured" /></td></tr>
                    @endforelse
                </x-admin.table>
            </div>
        </x-admin.card>

        <x-admin.card title="Add Courier" class="mt-6">
            <form method="POST" action="{{ route('admin.shipping-couriers.store') }}" data-v-form class="space-y-3">
                @csrf
                <div class="flex items-center gap-3">
                    <input type="text" name="name" required placeholder="e.g. GoGo Express"
                        class="v-control w-40 h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                    <input type="number" name="sort_order" value="0" min="0" placeholder="Order"
                        class="v-control w-16 h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                    <x-admin.checkbox name="is_active" :checked="true" label="Active" />
                </div>
                <div class="flex items-center gap-3">
                    <input type="text" name="tracking_url" placeholder="https://example.com/track/{tracking_number}"
                        class="v-control flex-1 h-[var(--control-h)] px-3 text-xs font-mono bg-v-surface text-v-text border border-v-border rounded-v-md">
                    <x-admin.button type="submit">Add Courier</x-admin.button>
                </div>
            </form>
        </x-admin.card>
</x-admin.settings-shell>
