<x-admin.shell title="Customers">
    <x-admin.page title="Customers">
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('admin.customer-groups.index') }}">Customer Groups</x-admin.button>
            <x-admin.button variant="primary" href="{{ route('admin.customers.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add Customer
            </x-admin.button>
        </x-slot:actions>

        <div>
            <x-admin.filters :action="route('admin.customers.index')">
                <x-admin.field name="search" label="Search" class="v-field--grow">
                    <x-admin.input type="text" name="search" :value="request('search')" placeholder="Search name, email, phone..." />
                </x-admin.field>
                <x-admin.field name="customer_group_id" label="Group" class="w-48">
                    <x-admin.select name="customer_group_id">
                        <option value="">All Groups</option>
                        @foreach ($customerGroups as $group)
                            <option value="{{ $group->id }}" {{ request('customer_group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>
                <x-admin.field name="status" label="Status" class="w-40">
                    <x-admin.select name="status">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </x-admin.select>
                </x-admin.field>
                <x-admin.button type="submit" variant="secondary">Filter</x-admin.button>
                @if (request()->anyFilled(['search', 'customer_group_id', 'status']))
                    <a href="{{ route('admin.customers.index') }}" class="v-filters__reset">Clear</a>
                @endif
            </x-admin.filters>

            {{-- Table --}}
            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.th>Customer</x-admin.table.th>
                    <x-admin.table.th>Email</x-admin.table.th>
                    <x-admin.table.th>Group</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th>Joined</x-admin.table.th>
                    <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                </x-slot:head>
                @forelse ($customers as $customer)
                    <x-admin.table.row href="{{ route('admin.customers.edit', $customer) }}">
                        <x-admin.table.cell>
                            <span class="v-lead">{{ $customer->full_name }}</span>
                            @if ($customer->phone)
                                <span class="v-sub">{{ $customer->phone }}</span>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell><span class="v-sub">{{ $customer->email }}</span></x-admin.table.cell>
                        <x-admin.table.cell>{{ $customer->customerGroup?->name ?? '-' }}</x-admin.table.cell>
                        <x-admin.table.cell>
                            <x-admin.badge :tone="$customer->is_active ? 'success' : 'neutral'">
                                {{ $customer->is_active ? 'Active' : 'Inactive' }}
                            </x-admin.badge>
                        </x-admin.table.cell>
                        <x-admin.table.cell>{{ $customer->created_at->format('M d, Y') }}</x-admin.table.cell>
                        <x-admin.table.cell class="v-col-act">
                            <div class="v-rowactions v-rowactions--end">
                                <x-admin.button size="sm" variant="ghost" :href="route('admin.customers.edit', $customer)">Edit</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-customer-{{ $customer->id }}')">Delete</x-admin.button>
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-admin.empty-state title="No customers found" :description="request()->anyFilled(['search', 'customer_group_id', 'status']) ? 'Try adjusting your filters.' : null">
                                <x-admin.button variant="primary" :href="route('admin.customers.create')">Add your first customer</x-admin.button>
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
