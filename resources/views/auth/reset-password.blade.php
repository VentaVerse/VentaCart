<x-admin.auth-shell title="Reset password" subtitle="Choose a new password for your account.">
    <form method="POST" action="{{ route('password.store') }}" data-v-form class="v3-authform">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-admin3.field name="email" label="Email" required>
            <input type="email" name="email" class="v3-control" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" />
        </x-admin3.field>

        <x-admin3.field name="password" label="New password" required>
            <input type="password" name="password" class="v3-control" required autocomplete="new-password" />
        </x-admin3.field>

        <x-admin3.field name="password_confirmation" label="Confirm new password" required>
            <input type="password" name="password_confirmation" class="v3-control" required autocomplete="new-password" />
        </x-admin3.field>

        <button type="submit" class="v3-btn v3-btn--primary v3-block-btn">Reset password</button>
    </form>
</x-admin.auth-shell>
