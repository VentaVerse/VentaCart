<x-admin.settings-shell title="Shipping Couriers">
        <x-admin.card title="Couriers">
            <x-slot name="headerActions">
                <x-admin.hint label="Tracking URL template" class="v-hint--end">Put {tracking_number} in the URL where the code belongs; it is substituted when an order is looked up.</x-admin.hint>
            </x-slot>

            <div x-data="{ editing: null }">
                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th>Courier</x-admin.table.th>
                        <x-admin.table.th>Tracking URL Template</x-admin.table.th>
                        <x-admin.table.th align="right">Order</x-admin.table.th>
                        <x-admin.table.th align="right">Active</x-admin.table.th>
                        <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                    </x-slot:head>
                    @forelse ($couriers as $courier)
                        <tr class="v-row">
                            {{-- Read mode --}}
                            <template x-if="editing !== {{ $courier->id }}">
                                <td class="px-4 py-2 align-middle text-[13px] font-medium text-v-text">{{ $courier->name }}</td>
                            </template>
                            <template x-if="editing !== {{ $courier->id }}">
                                <td class="v-num max-w-xs truncate px-4 py-2 align-middle text-xs text-v-secondary" title="{{ $courier->tracking_url }}">{{ $courier->tracking_url ?? '-' }}</td>
                            </template>
                            <template x-if="editing !== {{ $courier->id }}">
                                <td class="v-num px-4 py-2 align-middle text-right text-[13px] text-v-secondary">{{ $courier->sort_order }}</td>
                            </template>
                            <template x-if="editing !== {{ $courier->id }}">
                                <td class="px-4 py-2 align-middle text-right">
                                    <x-admin.badge :tone="$courier->is_active ? 'success' : 'neutral'">{{ $courier->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                                </td>
                            </template>
                            <template x-if="editing !== {{ $courier->id }}">
                                <td class="v-col-act">
                                    <div class="v-rowactions v-rowactions--end">
                                        <x-admin.button type="button" size="sm" variant="ghost" @click="editing = {{ $courier->id }}">Edit</x-admin.button>
                                        <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger"
                                            @click="$dispatch('open-modal', 'delete-courier-{{ $courier->id }}')">Delete</x-admin.button>
                                    </div>
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
                                            <input type="text" name="name" value="{{ $courier->name }}" required placeholder="Courier name" aria-label="Courier name"
                                                class="v-control h-[var(--control-h-sm)] w-40 rounded-v-md border bg-v-surface px-2.5 text-[13px] text-v-text">
                                            <input type="number" name="sort_order" value="{{ $courier->sort_order }}" min="0" aria-label="Sort order"
                                                class="v-control h-[var(--control-h-sm)] w-16 rounded-v-md border bg-v-surface px-2.5 text-[13px] text-v-text">
                                            <x-admin.checkbox name="is_active" :checked="$courier->is_active" label="Active" />
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <input type="text" name="tracking_url" value="{{ $courier->tracking_url }}" placeholder="https://example.com/track/{tracking_number}" aria-label="Tracking URL template"
                                                class="v-control h-[var(--control-h-sm)] flex-1 rounded-v-md border bg-v-surface px-2.5 font-mono text-xs text-v-text">
                                            <x-admin.button type="submit" size="sm">Save</x-admin.button>
                                            <x-admin.button variant="secondary" size="sm" type="button" @click="editing = null">Cancel</x-admin.button>
                                        </div>
                                    </form>
                                </td>
                            </template>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-admin.empty-state title="No couriers configured" /></td></tr>
                    @endforelse
                </x-admin.table>
            </div>
        </x-admin.card>

        <x-admin.card title="Add Courier" class="mt-6">
            <form method="POST" action="{{ route('admin.shipping-couriers.store') }}" data-v-form class="space-y-3">
                @csrf
                <div class="flex flex-wrap items-end gap-3">
                    <x-admin.field name="name" label="Courier" class="w-44">
                        <x-admin.input name="name" required placeholder="e.g. GoGo Express" />
                    </x-admin.field>
                    <x-admin.field name="sort_order" label="Order" class="w-20">
                        <x-admin.input name="sort_order" type="number" value="0" min="0" />
                    </x-admin.field>
                    <x-admin.checkbox name="is_active" :checked="true" label="Active" />
                </div>
                <div class="flex items-end gap-3">
                    <x-admin.field name="tracking_url" label="Tracking URL template" class="flex-1">
                        <x-admin.input name="tracking_url" class="font-mono" placeholder="https://example.com/track/{tracking_number}" />
                    </x-admin.field>
                    <x-admin.button type="submit">Add courier</x-admin.button>
                </div>
            </form>
        </x-admin.card>
</x-admin.settings-shell>
