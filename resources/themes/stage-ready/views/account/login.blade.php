<x-store-layout title="Sign In">
    <div class="container-store py-16">
        <div class="account-auth-card">
            <h1 class="account-auth-title text-center">SIGN IN</h1>

            @if ($errors->any())
                <div class="account-flash-error mb-6">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('customer.login.submit') }}">
                @csrf
                @if (!empty($redirect))
                    <input type="hidden" name="redirect" value="{{ $redirect }}">
                @endif

                <div class="space-y-4">
                    <div>
                        <label class="account-label">Email Address</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="account-input w-full">
                    </div>

                    <div>
                        <label class="account-label">Password</label>
                        <input type="password" name="password" required
                               class="account-input w-full">
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-[#777] cursor-pointer">
                            <input type="checkbox" name="remember" class="accent-[var(--color-primary)]">
                            Remember me
                        </label>
                    </div>

                    <button type="submit" class="account-btn-primary w-full">SIGN IN</button>
                </div>
            </form>

            <div class="divider-glow my-6"></div>

            <p class="text-center text-sm text-[#777]">
                Don't have an account?
                <a href="{{ route('customer.register', !empty($redirect) ? ['redirect' => $redirect] : []) }}" class="text-[var(--color-primary)] hover:underline">Create one</a>
            </p>
        </div>
    </div>
</x-store-layout>
