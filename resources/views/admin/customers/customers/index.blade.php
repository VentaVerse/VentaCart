<x-admin3.shell title="Customers" active="customers">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Customers</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.customer-groups.index') }}" class="v3-btn v3-btn--secondary">Customer Groups</a>
            <a href="{{ route('admin.customers.create') }}" class="v3-btn v3-btn--primary">Add customer</a>
        </div>
    </div>

    <div class="v3-settle">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="v3-filterbar">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, email, phone…"
                   aria-label="Search customers" class="v3-control v3-filterbar__grow">
            <select name="customer_group_id" aria-label="Filter by group" class="v3-control v3-w-name">
                <option value="">All groups</option>
                @foreach ($customerGroups as $group)
                    <option value="{{ $group->id }}" @selected((string) request('customer_group_id') === (string) $group->id)>{{ $group->name }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Filter by status" class="v3-control v3-w-name">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <button type="submit" class="v3-btn v3-btn--secondary">Filter</button>
            @if (request()->anyFilled(['search', 'customer_group_id', 'status']))
                <a href="{{ route('admin.customers.index') }}" class="v3-link">Clear</a>
            @endif
        </form>

        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Group</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.customers.edit', $customer) }}" class="v3-rowlink">{{ $customer->full_name }}</a>
                                    @if ($customer->phone)
                                        <span class="v3-cell-quiet v3-block v3-mono">{{ $customer->phone }}</span>
                                    @endif
                                </td>
                                <td class="v3-cell-quiet">{{ $customer->email }}</td>
                                <td>{{ $customer->customerGroup?->name ?? '-' }}</td>
                                <td>
                                    <span class="v3-badge v3-badge--{{ $customer->is_active ? 'good' : 'quiet' }}">
                                        {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="v3-cell-quiet">{{ $customer->created_at->format('M d, Y') }}</td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.customers.edit', $customer) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                            @click="$dispatch('v3-confirm', 'delete-customer-{{ $customer->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No customers found</p>
                                        <p class="v3-empty__line">{{ request()->anyFilled(['search', 'customer_group_id', 'status']) ? 'Try adjusting the filters.' : 'Customers who register on the storefront appear here.' }}</p>
                                        @unless (request()->anyFilled(['search', 'customer_group_id', 'status']))
                                            <a href="{{ route('admin.customers.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Add your first customer</a>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$customers" />

        @foreach ($customers as $customer)
            <x-admin3.confirm name="delete-customer-{{ $customer->id }}" title="Delete {{ $customer->full_name }}?"
                message="This permanently removes the customer, their addresses, and cart. This cannot be undone."
                confirm="Delete" :action="route('admin.customers.destroy', $customer)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
