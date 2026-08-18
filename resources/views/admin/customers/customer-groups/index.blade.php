<x-admin.shell title="Customer Groups" active="customers">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.customers.index') }}">Customers</a>
                <span aria-hidden="true">/</span>
                <span>Groups</span>
            </div>
            <h1 class="v-head__title">Customer Groups</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.customer-groups.create') }}" class="v-btn v-btn--primary">Add group</a>
        </div>
    </div>

    <div class="v-settle">
        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th class="v-cell-num">Customers</th>
                            <th>Default</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.customer-groups.edit', $group) }}" class="v-rowlink">{{ $group->name }}</a>
                                    @if ($group->description)
                                        <span class="v-cell-quiet v-block">{{ Str::limit($group->description, 60) }}</span>
                                    @endif
                                </td>
                                <td class="v-cell-num v-mono">{{ $group->customers_count }}</td>
                                <td>
                                    @if ($group->is_default)
                                        <span class="v-badge v-badge--good">Default</span>
                                    @endif
                                </td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.customer-groups.edit', $group) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    @if ($group->customers_count === 0)
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                                @click="$dispatch('v-confirm', 'delete-group-{{ $group->id }}')">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No customer groups found</p>
                                        <a href="{{ route('admin.customer-groups.create') }}" class="v-btn v-btn--primary v-btn--sm">Create your first group</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <x-admin.pager :paginator="$groups" />

        @foreach ($groups as $group)
            @if ($group->customers_count === 0)
                <x-admin.confirm name="delete-group-{{ $group->id }}" title="Delete {{ $group->name }}?"
                    message="This cannot be undone." confirm="Delete" :action="route('admin.customer-groups.destroy', $group)" method="DELETE" />
            @endif
        @endforeach
    </div>
</x-admin.shell>
