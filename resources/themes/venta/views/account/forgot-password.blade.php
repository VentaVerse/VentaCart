<x-store-layout title="Forgot Password">
    <div class="container-store py-16">
        <div class="account-auth-card">
            <h1 class="account-auth-title text-center">FORGOT PASSWORD</h1>
            <p class="text-sm text-[var(--color-text)] text-center mb-6">Enter your email address and we'll send you a link to reset your password.</p>

            @if (session('status'))
                <div class="account-flash-success mb-6">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="account-flash-error mb-6">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('customer.password.email') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="account-label">Email Address</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="account-input w-full">
                    </div>

                    <x-turnstile />

                    <button type="submit" class="account-btn-primary w-full">SEND RESET LINK</button>
                </div>
            </form>

            <div class="divider-glow my-6"></div>

            <p class="text-center text-sm text-[var(--color-text)]">
                Remember your password?
                <a href="{{ route('customer.login') }}" class="text-[var(--color-primary)] hover:underline">Sign in</a>
            </p>
        </div>
    </div>
</x-store-layout>
