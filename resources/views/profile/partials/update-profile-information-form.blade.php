<x-admin.form :action="route('admin.profile.update')" method="PATCH" :header-save="false">
    <x-admin.panel title="Profile Information">
        <div class="v-grid v-grid--2">
            <x-admin.field name="name" label="Name" required>
                <input type="text" name="name" required autocomplete="name" class="v-control" value="{{ old('name', $user->name) }}">
            </x-admin.field>
            <x-admin.field name="email" label="Email" required>
                <input type="email" name="email" required autocomplete="username" class="v-control" value="{{ old('email', $user->email) }}">
            </x-admin.field>
        </div>
        @if (session('status') === 'profile-updated')
            <p class="v-cell-quiet v-gap-sm" x-data="{ show: true }" x-show="show" x-transition
               x-init="setTimeout(() => show = false, 2000)" role="status">Saved.</p>
        @endif
    </x-admin.panel>
</x-admin.form>
