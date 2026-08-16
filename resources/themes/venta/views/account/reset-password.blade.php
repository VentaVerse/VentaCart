<x-store-layout title="Reset Password">
    <div class="container-store py-16">
        <div class="account-auth-card">
            <h1 class="account-auth-title text-center">SET NEW PASSWORD</h1>

            @if ($errors->any())
                <div class="account-flash-error mb-6">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('customer.password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="space-y-4">
                    <div>
                        <label class="account-label">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $email) }}" required
                               class="account-input w-full">
                    </div>

                    <div>
                        <label class="account-label">New Password</label>
                        <input type="password" name="password" required autocomplete="new-password"
                               class="account-input w-full">
                    </div>

                    <div>
                        <label class="account-label">Confirm Password</label>
                        <input type="password" name="password_confirmation" required autocomplete="new-password"
                               class="account-input w-full">
                    </div>

                    <button type="submit" class="account-btn-primary w-full">RESET PASSWORD</button>
                </div>
            </form>
        </div>
    </div>
</x-store-layout>
