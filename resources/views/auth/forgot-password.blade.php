<x-admin.auth-shell title="Forgot password" subtitle="Enter your email address and we'll send you a link to reset your password.">
    <form method="POST" action="{{ route('password.email') }}" data-v-form class="v-authform">
        @csrf

        <x-admin.field name="email" label="Email" required>
            <input type="email" name="email" class="v-control" value="{{ old('email') }}" required autofocus autocomplete="username" />
        </x-admin.field>

        <button type="submit" class="v-btn v-btn--primary v-block-btn">Email password reset link</button>
    </form>

    <p class="mt-6 text-center text-[13px] text-v-secondary">
        <a href="{{ route('login') }}" class="hover:text-v-text">Back to sign in</a>
    </p>
</x-admin.auth-shell>
