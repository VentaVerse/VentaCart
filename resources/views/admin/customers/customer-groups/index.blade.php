<x-admin3.shell title="Customer Groups" active="customers">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.customers.index') }}">Customers</a>
                <span aria-hidden="true">/</span>
                <span>Groups</span>
            </div>
            <h1 class="v3-head__title">Customer Groups</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.customer-groups.create') }}" class="v3-btn v3-btn--primary">Add group</a>
        </div>
    </div>

    <div class="v3-settle">
        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th class="v3-cell-num">Customers</th>
                            <th>Default</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.customer-groups.edit', $group) }}" class="v3-rowlink">{{ $group->name }}</a>
                                    @if ($group->description)
                                        <span class="v3-cell-quiet v3-block">{{ Str::limit($group->description, 60) }}</span>
                                    @endif
                                </td>
                                <td class="v3-cell-num v3-mono">{{ $group->customers_count }}</td>
                                <td>
                                    @if ($group->is_default)
                                        <span class="v3-badge v3-badge--good">Default</span>
                                    @endif
                                </td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.customer-groups.edit', $group) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    @if ($group->customers_count === 0)
                                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm"
                                                @click="$dispatch('v3-confirm', 'delete-group-{{ $group->id }}')">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No customer groups found</p>
                                        <a href="{{ route('admin.customer-groups.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Create your first group</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        <x-admin3.pager :paginator="$groups" />

        @foreach ($groups as $group)
            @if ($group->customers_count === 0)
                <x-admin3.confirm name="delete-group-{{ $group->id }}" title="Delete {{ $group->name }}?"
                    message="This cannot be undone." confirm="Delete" :action="route('admin.customer-groups.destroy', $group)" method="DELETE" />
            @endif
        @endforeach
    </div>
</x-admin3.shell>
