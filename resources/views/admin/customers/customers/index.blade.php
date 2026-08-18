<x-admin.shell title="Customers" active="customers">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Customers</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.customer-groups.index') }}" class="v-btn v-btn--secondary">Customer Groups</a>
            <a href="{{ route('admin.customers.create') }}" class="v-btn v-btn--primary">Add customer</a>
        </div>
    </div>

    <div class="v-settle">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="v-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, email, phone…"
                   aria-label="Search customers" class="v-control v-filterbar__grow">
            <select name="customer_group_id" aria-label="Filter by group" class="v-control v-w-name">
                <option value="">All groups</option>
                @foreach ($customerGroups as $group)
                    <option value="{{ $group->id }}" @selected((string) request('customer_group_id') === (string) $group->id)>{{ $group->name }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter by status" class="v-control v-w-name">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <button type="submit" class="v-btn v-btn--secondary">Filter</button>
            @if (request()->anyFilled(['search', 'customer_group_id', 'status']))
                <a href="{{ route('admin.customers.index') }}" class="v-link">Clear</a>
            @endif
        </form>

        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Group</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.customers.edit', $customer) }}" class="v-rowlink">{{ $customer->full_name }}</a>
                                    @if ($customer->phone)
                                        <span class="v-cell-quiet v-block v-mono">{{ $customer->phone }}</span>
                                    @endif
                                </td>
                                <td class="v-cell-quiet">{{ $customer->email }}</td>
                                <td>{{ $customer->customerGroup?->name ?? '-' }}</td>
                                <td>
                                    <span class="v-badge v-badge--{{ $customer->is_active ? 'good' : 'quiet' }}">
                                        {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="v-cell-quiet">{{ $customer->created_at->format('M d, Y') }}</td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.customers.edit', $customer) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                            @click="$dispatch('v-confirm', 'delete-customer-{{ $customer->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No customers found</p>
                                        <p class="v-empty__line">{{ request()->anyFilled(['search', 'customer_group_id', 'status']) ? 'Try adjusting the filters.' : 'Customers who register on the storefront appear here.' }}</p>
                                        @unless (request()->anyFilled(['search', 'customer_group_id', 'status']))
                                            <a href="{{ route('admin.customers.create') }}" class="v-btn v-btn--primary v-btn--sm">Add your first customer</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$customers" />

        @foreach ($customers as $customer)
            <x-admin.confirm name="delete-customer-{{ $customer->id }}" title="Delete {{ $customer->full_name }}?"
                message="This permanently removes the customer, their addresses, and cart. This cannot be undone."
                confirm="Delete" :action="route('admin.customers.destroy', $customer)" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
