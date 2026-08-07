<x-admin.auth-shell title="Reset password" subtitle="Choose a new password for your account.">
    <form method="POST" action="{{ route('password.store') }}" data-v-form class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-admin.field name="email" label="Email">
            <x-admin.input type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
        </x-admin.field>

        <x-admin.field name="password" label="New password">
            <x-admin.input type="password" name="password" required autocomplete="new-password" />
        </x-admin.field>

        <x-admin.field name="password_confirmation" label="Confirm new password">
            <x-admin.input type="password" name="password_confirmation" required autocomplete="new-password" />
        </x-admin.field>

        <x-admin.button type="submit" class="w-full">Reset password</x-admin.button>
    </form>
</x-admin.auth-shell>
