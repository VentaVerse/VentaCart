<x-admin.settings-shell title="Users" active="users">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Users</h1>
            <p class="v-head__standing">
                @if ($groupFilter ?? null)
                    In the {{ $groupFilter->name }} group. <a href="{{ route('admin.users.index') }}" class="v-link">Show everyone</a>
                @else
                    Who can sign into this admin, and as what.
                @endif
            </p>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.users.create') }}" class="v-btn v-btn--primary">
                <x-admin.icon name="plus" /> Add user
            </a>
        </div>
    </div>

    <div class="v-settle">
        <x-admin.panel title="Admin accounts" flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Group</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>
                                    <span class="v-ava" aria-hidden="true">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                    <a href="{{ route('admin.users.edit', $user) }}" class="v-cell-link">{{ $user->name }}</a>
                                </td>
                                <td class="v-mono v-cell-quiet">{{ $user->username }}</td>
                                <td class="v-cell-quiet">{{ $user->email }}</td>
                                <td>
                                    @if ($user->role)
                                        <span class="v-badge {{ $user->role->slug === 'super-admin' ? 'v-badge--bad' : 'v-badge--quiet' }}">{{ $user->role->name }}</span>
                                    @else
                                        <span class="v-badge v-badge--quiet">No group</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="v-badge {{ $user->is_active ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="v-cell-quiet">{{ $user->created_at->format('M j, Y') }}</td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    @if ($user->id !== auth()->id())
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-user-{{ $user->id }}')">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No users found</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        @foreach ($users as $user)
            @if ($user->id !== auth()->id())
                <x-admin.confirm name="delete-user-{{ $user->id }}" title="Delete {{ $user->name }}?"
                    message="This cannot be undone. Their sessions end and their sign-in stops working immediately."
                    confirm="Delete" :action="route('admin.users.destroy', $user)" method="DELETE" />
            @endif
        @endforeach
    </div>
</x-admin.settings-shell>
