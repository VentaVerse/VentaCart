<x-admin.settings-shell title="User Groups" active="user-groups">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">User Groups</h1>
            <p class="v-head__standing">What each group of admin accounts may touch.</p>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.user-groups.create') }}" class="v-btn v-btn--primary">Add group</a>
        </div>
    </div>

    <div class="v-settle">
        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th class="v-cell-num">Users</th>
                            <th class="v-cell-num">Permissions</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.user-groups.edit', $group) }}" class="v-rowlink">{{ $group->name }}</a>
                                    @if ($group->slug === 'super-admin')
                                        <span class="v-badge v-badge--bad">Full access</span>
                                    @endif
                                    @if ($group->description)
                                        <span class="v-cell-quiet v-block">{{ Str::limit($group->description, 60) }}</span>
                                    @endif
                                </td>
                                <td class="v-cell-num v-mono">{{ $group->users_count }}</td>
                                <td class="v-cell-num v-mono">{{ $group->permissions_count }} <span class="v-cell-quiet">/ {{ $permissionTotal }}</span></td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.user-groups.edit', $group) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    @if ($group->slug !== 'super-admin' && $group->users_count === 0)
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                                @click="$dispatch('v-confirm', 'delete-group-{{ $group->id }}')">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No user groups found</p>
                                        <a href="{{ route('admin.user-groups.create') }}" class="v-btn v-btn--primary v-btn--sm">Create your first group</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        @foreach ($groups as $group)
            @if ($group->slug !== 'super-admin' && $group->users_count === 0)
                <x-admin.confirm name="delete-group-{{ $group->id }}" title="Delete {{ $group->name }}?"
                    message="This cannot be undone." confirm="Delete" :action="route('admin.user-groups.destroy', $group)" method="DELETE" />
            @endif
        @endforeach
    </div>
</x-admin.settings-shell>
