<x-admin.shell title="Order Statuses">
    <x-admin.page title="Order Statuses" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')]]">
        <div class="space-y-5">
            <x-admin.card title="Stock actions" description="What happens to inventory when an order enters a status.">
                <div class="flex flex-wrap gap-4 text-[13px]">
                    <span class="flex items-center gap-2"><x-admin.badge tone="danger">▼ Decrements</x-admin.badge><span class="text-v-secondary">stock committed (e.g. Processing)</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="success">▲ Increments</x-admin.badge><span class="text-v-secondary">stock returned (e.g. Refunded, Canceled)</span></span>
                    <span class="flex items-center gap-2"><x-admin.badge tone="neutral">—</x-admin.badge><span class="text-v-secondary">no inventory effect</span></span>
                </div>
            </x-admin.card>

            <x-admin.card title="Statuses">
                {{--
                    A <form> cannot legally wrap table rows/cells. Each row's edit form is
                    self-contained inside its own <td> (spanning the row via a nested table),
                    and the delete button lives OUTSIDE that form, as a sibling that opens the
                    confirm-dialog modal (which renders its own form).
                --}}
                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th>Stock action</x-admin.table.th>
                        <x-admin.table.th>Sort</x-admin.table.th>
                        <x-admin.table.th align="right">Actions</x-admin.table.th>
                    </x-slot:head>
                    @foreach ($statuses as $status)
                        <x-admin.table.row>
                            <td class="px-0 py-0" colspan="4">
                                <form method="POST" action="{{ route('admin.order-statuses.update', $status) }}" data-v-form class="w-full">
                                    @csrf @method('PUT')
                                    <table class="w-full">
                                        <tr>
                                            <td class="px-4 py-2 align-middle">
                                                <div class="flex items-center gap-2">
                                                    <input type="text" name="name" value="{{ old('name', $status->name) }}" required maxlength="255"
                                                        class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                    @if ($status->is_default)
                                                        <x-admin.badge tone="success">Default</x-admin.badge>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-2 align-middle">
                                                <div class="flex items-center gap-2">
                                                    <select name="stock_action" class="v-control h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                                        <option value="" @selected(! $status->stock_action)>None</option>
                                                        <option value="decrement" @selected($status->stock_action === 'decrement')>Decrement</option>
                                                        <option value="increment" @selected($status->stock_action === 'increment')>Increment</option>
                                                    </select>
                                                    @if ($status->stock_action === 'decrement')
                                                        <x-admin.badge tone="danger">▼ Decrements</x-admin.badge>
                                                    @elseif ($status->stock_action === 'increment')
                                                        <x-admin.badge tone="success">▲ Increments</x-admin.badge>
                                                    @else
                                                        <x-admin.badge tone="neutral">—</x-admin.badge>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-2 align-middle">
                                                <input type="number" name="sort_order" value="{{ old('sort_order', $status->sort_order) }}" min="0"
                                                    class="v-control w-20 h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                            </td>
                                            <td class="px-4 py-2 align-middle text-right">
                                                <div class="flex items-center justify-end gap-3">
                                                    <label class="inline-flex items-center gap-1.5 text-[13px] text-v-text cursor-pointer">
                                                        <input type="hidden" name="is_default" value="0">
                                                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $status->is_default))
                                                            class="w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                                        <span>Default</span>
                                                    </label>
                                                    <x-admin.button variant="secondary" size="sm" type="submit">Save</x-admin.button>
                                                    @unless ($status->is_default)
                                                        <x-admin.button variant="ghost" size="sm" type="button" class="text-v-danger hover:text-v-danger" @click="$dispatch('open-modal', 'delete-status-{{ $status->id }}')">Delete</x-admin.button>
                                                    @endunless
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
                            </td>
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
                <form method="POST" action="{{ route('admin.order-statuses.store') }}" data-v-form class="grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
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
    </x-admin.page>
</x-admin.shell>
