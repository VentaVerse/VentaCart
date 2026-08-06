<x-admin.auth-shell title="Forgot password" subtitle="Enter your email address and we'll send you a link to reset your password.">
    <form method="POST" action="{{ route('password.email') }}" data-v-form class="space-y-5">
        @csrf

        <x-admin.field name="email" label="Email">
            <x-admin.input type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="name@company.com" />
        </x-admin.field>

        <x-admin.button type="submit" class="w-full">Email password reset link</x-admin.button>
    </form>

    <p class="mt-6 text-center text-[13px] text-v-secondary">
        <a href="{{ route('login') }}" class="hover:text-v-text">Back to sign in</a>
    </p>
</x-admin.auth-shell>
