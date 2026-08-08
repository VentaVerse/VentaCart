<x-admin.shell title="Customers">
    <x-admin.page title="Customers">
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('admin.customer-groups.index') }}">Customer Groups</x-admin.button>
            <x-admin.button variant="primary" href="{{ route('admin.customers.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Customer
            </x-admin.button>
        </x-slot:actions>

        <div class="space-y-5">
            {{-- Filters --}}
            <x-admin.card>
                <form method="GET" action="{{ route('admin.customers.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <x-admin.field name="search" label="Search">
                            <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search name, email, phone..." />
                        </x-admin.field>
                    </div>
                    <div class="w-48">
                        <x-admin.field name="customer_group_id" label="Group">
                            <x-admin.select name="customer_group_id">
                                <option value="">All Groups</option>
                                @foreach ($customerGroups as $group)
                                    <option value="{{ $group->id }}" {{ request('customer_group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                                @endforeach
                            </x-admin.select>
                        </x-admin.field>
                    </div>
                    <div class="w-40">
                        <x-admin.field name="status" label="Status">
                            <x-admin.select name="status">
                                <option value="">All Status</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </x-admin.select>
                        </x-admin.field>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-admin.button type="submit" variant="primary">Filter</x-admin.button>
                        @if (request()->anyFilled(['search', 'customer_group_id', 'status']))
                            <a href="{{ route('admin.customers.index') }}" class="text-[13px] text-v-muted underline hover:text-v-text">Clear</a>
                        @endif
                    </div>
                </form>
            </x-admin.card>

            {{-- Table --}}
            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Customer</x-admin.table.th>
                    <x-admin.table.th>Email</x-admin.table.th>
                    <x-admin.table.th>Group</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th>Joined</x-admin.table.th>
                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($customers as $customer)
                    <x-admin.table.row href="{{ route('admin.customers.edit', $customer) }}">
                        <x-admin.table.cell>
                            <div class="text-[13px] font-medium text-v-text">{{ $customer->full_name }}</div>
                            @if ($customer->phone)
                                <div class="text-xs text-v-muted">{{ $customer->phone }}</div>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell>{{ $customer->email }}</x-admin.table.cell>
                        <x-admin.table.cell>{{ $customer->customerGroup?->name ?? '-' }}</x-admin.table.cell>
                        <x-admin.table.cell>
                            <x-admin.badge :tone="$customer->is_active ? 'success' : 'neutral'">
                                {{ $customer->is_active ? 'Active' : 'Inactive' }}
                            </x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell>{{ $customer->created_at->format('M d, Y') }}</x-admin.table.cell>
                        <x-admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.customers.edit', $customer) }}" class="text-[13px] text-v-accent hover:opacity-80">Edit</a>
                                <button type="button" class="text-[13px] text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-customer-{{ $customer->id }}')">Delete</button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-2">
                            <x-admin.empty-state icon="customers" title="No customers found" :description="request()->anyFilled(['search', 'customer_group_id', 'status']) ? 'Try adjusting your filters.' : null">
                                <a href="{{ route('admin.customers.create') }}" class="text-[13px] text-v-accent hover:underline">Add your first customer</a>
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
                @if ($customers->hasPages())
                    <x-slot:footer>
                        {{ $customers->links() }}
                    </x-slot:footer>
                @endif
            </x-admin.table>
        </div>

        @foreach ($customers as $customer)
            <x-admin.confirm-dialog name="delete-customer-{{ $customer->id }}" title="Delete {{ $customer->full_name }}?"
                message="This permanently removes the customer, their addresses, and cart. This cannot be undone."
                confirm="Delete" :action="route('admin.customers.destroy', $customer)" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
