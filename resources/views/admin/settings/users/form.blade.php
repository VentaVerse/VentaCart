<x-admin.settings-shell :title="$user ? 'Edit User' : 'Create User'">
        <div class="max-w-lg">
            <x-admin.card>
                <form method="POST" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}" data-v-form>
                    @csrf
                    @if ($user)
                        @method('PUT')
                    @endif

                    <div class="space-y-5">
                        <x-admin.field name="name" label="Name" required>
                            <x-admin.input type="text" name="name" required :value="$user->name ?? ''" />
                        </x-admin.field>

                        <x-admin.field name="username" label="Username" required>
                            <x-admin.input type="text" name="username" required :value="$user->username ?? ''" autocomplete="username" />
                        </x-admin.field>

                        <x-admin.field name="email" label="Email" required>
                            <x-admin.input type="email" name="email" required :value="$user->email ?? ''" />
                        </x-admin.field>

                        <x-admin.field name="password" label="Password" :help="$user ? 'Leave blank to keep current password.' : null" :required="! $user">
                            <x-admin.input type="password" name="password" autocomplete="new-password" :required="! $user" />
                        </x-admin.field>

                        <x-admin.field name="role_id" label="Role" required>
                            <x-admin.select name="role_id" required>
                                <option value="">-- Select Role --</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" {{ old('role_id', $user->role_id ?? '') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                                @endforeach
                            </x-admin.select>
                        </x-admin.field>

                        <div class="flex items-center gap-3">
                            <x-admin.toggle name="is_active" :checked="$user->is_active ?? true" />
                            <label for="is_active" class="text-[13px] font-medium text-v-text cursor-pointer">Active</label>
                        </div>
                    </div>

                    <x-admin.form-actions>
                        <x-admin.button variant="secondary" href="{{ route('admin.users.index') }}">Cancel</x-admin.button>
                        <x-admin.button type="submit">{{ $user ? 'Update User' : 'Create User' }}</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        </div>
</x-admin.settings-shell>
