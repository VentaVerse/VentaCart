<x-admin3.settings-shell title="Users" active="users">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Users</h1>
            <p class="v3-head__standing">Who can sign into this admin, and as what.</p>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.users.create') }}" class="v3-btn v3-btn--primary">
                <x-admin3.icon name="plus" /> Add user
            </a>
        </div>
    </div>

    <div class="v3-settle">
        <x-admin3.panel title="Admin accounts" flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>
                                    <span class="v3-ava" aria-hidden="true">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                    <a href="{{ route('admin.users.edit', $user) }}" class="v3-cell-link">{{ $user->name }}</a>
                                </td>
                                <td class="v3-mono v3-cell-quiet">{{ $user->username }}</td>
                                <td class="v3-cell-quiet">{{ $user->email }}</td>
                                <td>
                                    @if ($user->role)
                                        <span class="v3-badge {{ $user->role->slug === 'super-admin' ? 'v3-badge--bad' : 'v3-badge--quiet' }}">{{ $user->role->name }}</span>
                                    @else
                                        <span class="v3-badge v3-badge--quiet">No role</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="v3-badge {{ $user->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="v3-cell-quiet">{{ $user->created_at->format('M j, Y') }}</td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    @if ($user->id !== auth()->id())
                                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-user-{{ $user->id }}')">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No users found</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        @foreach ($users as $user)
            @if ($user->id !== auth()->id())
                <x-admin3.confirm name="delete-user-{{ $user->id }}" title="Delete {{ $user->name }}?"
                    message="This cannot be undone. Their sessions end and their sign-in stops working immediately."
                    confirm="Delete" :action="route('admin.users.destroy', $user)" method="DELETE" />
            @endif
        @endforeach
    </div>
</x-admin3.settings-shell>
