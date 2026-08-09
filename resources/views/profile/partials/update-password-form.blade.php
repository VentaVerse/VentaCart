<x-admin.card title="Update Password">
    <form method="post" action="{{ route('password.update') }}" data-v-form class="space-y-5">
        @csrf
        @method('put')

        <x-admin.field name="current_password" label="Current Password" required>
            <x-admin.input name="current_password" type="password" autocomplete="current-password" />
            @error('current_password', 'updatePassword')
                <p class="text-xs text-v-danger">{{ $message }}</p>
            @enderror
        </x-admin.field>

        <x-admin.field name="password" label="New Password" required>
            <x-admin.input name="password" type="password" autocomplete="new-password" />
            @error('password', 'updatePassword')
                <p class="text-xs text-v-danger">{{ $message }}</p>
            @enderror
        </x-admin.field>

        <x-admin.field name="password_confirmation" label="Confirm Password" required>
            <x-admin.input name="password_confirmation" type="password" autocomplete="new-password" />
            @error('password_confirmation', 'updatePassword')
                <p class="text-xs text-v-danger">{{ $message }}</p>
            @enderror
        </x-admin.field>

        <x-admin.form-actions>
            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-[13px] text-v-muted"
                >Saved.</p>
            @endif
            <x-admin.button type="submit">Save</x-admin.button>
        </x-admin.form-actions>
    </form>
</x-admin.card>
