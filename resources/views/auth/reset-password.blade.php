<x-admin.auth-shell title="Reset password" subtitle="Choose a new password for your account.">
    <form method="POST" action="{{ route('password.store') }}" data-v-form class="v-authform">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-admin.field name="email" label="Email" required>
            <input type="email" name="email" class="v-control" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" />
        </x-admin.field>

        <x-admin.field name="password" label="New password" required>
            <input type="password" name="password" class="v-control" required autocomplete="new-password" />
        </x-admin.field>

        <x-admin.field name="password_confirmation" label="Confirm new password" required>
            <input type="password" name="password_confirmation" class="v-control" required autocomplete="new-password" />
        </x-admin.field>

        <button type="submit" class="v-btn v-btn--primary v-block-btn">Reset password</button>
    </form>
</x-admin.auth-shell>
