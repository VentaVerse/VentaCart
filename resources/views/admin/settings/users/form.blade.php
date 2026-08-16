<x-admin3.settings-shell :title="$user ? 'Edit user' : 'Add user'" active="users">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.users.index') }}">Users</a>
                <span aria-hidden="true">/</span>
                <span>{{ $user->name ?? 'New' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $user ? $user->name : 'Add user' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$user ? route('admin.users.update', $user) : route('admin.users.store')"
                   :method="$user ? 'PUT' : 'POST'" class="v3-settle">
        <x-admin3.panel title="Account">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="name" label="Name" required>
                    <input type="text" name="name" required class="v3-control" value="{{ old('name', $user->name ?? '') }}">
                </x-admin3.field>
                <x-admin3.field name="username" label="Username" required>
                    <input type="text" name="username" required autocomplete="username" class="v3-control v3-mono" value="{{ old('username', $user->username ?? '') }}">
                </x-admin3.field>
                <x-admin3.field name="email" label="Email" required>
                    <input type="email" name="email" required class="v3-control" value="{{ old('email', $user->email ?? '') }}">
                </x-admin3.field>
                <x-admin3.field name="password" label="Password" :required="! $user"
                    :hint="$user ? 'Leave blank to keep the current password.' : null">
                    <input type="password" name="password" autocomplete="new-password" class="v3-control" @required(! $user)>
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="role_id" label="Role" required>
                    <select name="role_id" required class="v3-control">
                        <option value="">Select role…</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id ?? '') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <label class="v3-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Active
                </label>
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.settings-shell>
