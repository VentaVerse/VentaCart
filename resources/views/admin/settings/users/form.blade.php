<x-admin.settings-shell :title="$user ? 'Edit user' : 'Add user'" active="users">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.users.index') }}">Users</a>
                <span aria-hidden="true">/</span>
                <span>{{ $user->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $user ? $user->name : 'Add user' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$user ? route('admin.users.update', $user) : route('admin.users.store')"
                   :method="$user ? 'PUT' : 'POST'" class="v-settle">
        <x-admin.panel title="Account">
            <div class="v-grid v-grid--2">
                <x-admin.field name="name" label="Name" required>
                    <input type="text" name="name" required class="v-control" value="{{ old('name', $user->name ?? '') }}">
                </x-admin.field>
                <x-admin.field name="username" label="Username" required>
                    <input type="text" name="username" required autocomplete="username" class="v-control v-mono" value="{{ old('username', $user->username ?? '') }}">
                </x-admin.field>
                <x-admin.field name="email" label="Email" required>
                    <input type="email" name="email" required class="v-control" value="{{ old('email', $user->email ?? '') }}">
                </x-admin.field>
                @if ($user)
                    {{-- Same guard as the customer form: no password field in the DOM
                         until asked, so the browser sees no login form to autofill. --}}
                    <div x-data="{ changing: {{ old('password') ? 'true' : 'false' }} }">
                        <div class="v-label v-gap-sm">Password</div>
                        <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-show="!changing" @click="changing = true">Change password</button>
                        <template x-if="changing">
                            <x-admin.field name="password" label="New password">
                                <input type="password" name="password" autocomplete="new-password" class="v-control" x-init="$nextTick(() => $el.focus())">
                            </x-admin.field>
                        </template>
                    </div>
                @else
                    <x-admin.field name="password" label="Password" required>
                        <input type="password" name="password" autocomplete="new-password" class="v-control" required>
                    </x-admin.field>
                @endif
            </div>
            <div class="v-gap">
                <x-admin.field name="role_id" label="User group" required>
                    <select name="role_id" required class="v-control">
                        <option value="">Select group…</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id ?? '') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
            </div>
            <div class="v-gap">
                <label class="v-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Active
                </label>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.settings-shell>
