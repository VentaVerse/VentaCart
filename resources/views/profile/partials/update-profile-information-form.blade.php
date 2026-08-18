<x-admin3.form :action="route('admin.profile.update')" method="PATCH">
    <x-admin3.panel title="Profile Information">
        <div class="v3-grid v3-grid--2">
            <x-admin3.field name="name" label="Name" required>
                <input type="text" name="name" required autocomplete="name" class="v3-control" value="{{ old('name', $user->name) }}">
            </x-admin3.field>
            <x-admin3.field name="email" label="Email" required>
                <input type="email" name="email" required autocomplete="username" class="v3-control" value="{{ old('email', $user->email) }}">
            </x-admin3.field>
        </div>
        @if (session('status') === 'profile-updated')
            <p class="v3-cell-quiet v3-gap-sm" x-data="{ show: true }" x-show="show" x-transition
               x-init="setTimeout(() => show = false, 2000)" role="status">Saved.</p>
        @endif
    </x-admin3.panel>
</x-admin3.form>
