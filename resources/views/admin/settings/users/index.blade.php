<x-admin.settings-shell title="Users" :wide="true">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.users.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> Add User
            </x-admin.button>
        </x-slot:actions>

        <x-admin.table>
            <x-slot:head>
                <x-admin.table.th>Name</x-admin.table.th>
                <x-admin.table.th>Username</x-admin.table.th>
                <x-admin.table.th>Email</x-admin.table.th>
                <x-admin.table.th>Role</x-admin.table.th>
                <x-admin.table.th>Status</x-admin.table.th>
                <x-admin.table.th>Created</x-admin.table.th>
                <x-admin.table.th align="right">Actions</x-admin.table.th>
            </x-slot:head>
            @forelse ($users as $user)
                <x-admin.table.row href="{{ route('admin.users.edit', $user) }}">
                    <x-admin.table.cell>
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-v-accent-weak text-xs font-bold text-v-accent">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <span class="text-[13px] font-medium text-v-text">{{ $user->name }}</span>
                        </div>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        <span class="text-[13px] text-v-secondary">{{ $user->username }}</span>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        <span class="text-[13px] text-v-secondary">{{ $user->email }}</span>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        @if ($user->role)
                            @php
                                $tone = match ($user->role->slug) {
                                    'super-admin' => 'danger',
                                    'store-manager' => 'info',
                                    default => 'neutral',
                                };
                            @endphp
                            <x-admin.badge :tone="$tone">{{ $user->role->name }}</x-admin.badge>
                        @else
                            <span class="text-xs text-v-muted">No role</span>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        <x-admin.badge :tone="$user->is_active ? 'success' : 'neutral'">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </x-admin.badge>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        <span class="text-[13px] text-v-secondary">{{ $user->created_at->format('M j, Y') }}</span>
                    </x-admin.table.cell>
                    <x-admin.table.cell align="right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-[13px] text-v-accent hover:opacity-80">Edit</a>
                            @if ($user->id !== auth()->id())
                                <button type="button" class="text-[13px] text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-user-{{ $user->id }}')">Delete</button>
                            @endif
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-2">
                        <x-admin.empty-state icon="customers" title="No users found." />
                    </td>
                </tr>
            @endforelse
        </x-admin.table>

        @foreach ($users as $user)
            @if ($user->id !== auth()->id())
                <x-admin.confirm-dialog name="delete-user-{{ $user->id }}" title="Delete {{ $user->name }}?"
                    message="This cannot be undone." confirm="Delete" :action="route('admin.users.destroy', $user)" method="DELETE" />
            @endif
        @endforeach
</x-admin.settings-shell>
