<x-store-layout title="Create Account">
    <x-storefront.columns>
    <x-storefront.region name="content_top" />
    <div class="container-store py-16">
        <div class="account-auth-card">
            <h1 class="account-auth-title text-center">CREATE ACCOUNT</h1>

            @if ($errors->any())
                <div class="account-flash-error mb-6">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('customer.register.submit') }}">
                @csrf
                @if (!empty($redirect))
                    <input type="hidden" name="redirect" value="{{ $redirect }}">
                @endif

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="account-label">First Name</label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" required
                                   class="account-input w-full">
                        </div>
                        <div>
                            <label class="account-label">Last Name</label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" required
                                   class="account-input w-full">
                        </div>
                    </div>

                    <div>
                        <label class="account-label">Email Address</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="account-input w-full">
                    </div>

                    <div>
                        <label class="account-label">Password</label>
                        <input type="password" name="password" required
                               class="account-input w-full">
                    </div>

                    <div>
                        <label class="account-label">Confirm Password</label>
                        <input type="password" name="password_confirmation" required
                               class="account-input w-full">
                    </div>

                    <label class="flex items-center gap-2 text-sm text-[var(--color-text)] cursor-pointer">
                        <input type="checkbox" name="newsletter" value="1" {{ old('newsletter') ? 'checked' : '' }}
                               class="accent-[var(--color-primary)]">
                        Subscribe to newsletter
                    </label>

                    <x-turnstile />

                    <button type="submit" class="account-btn-primary w-full">CREATE ACCOUNT</button>
                </div>
            </form>

            <div class="divider-glow my-6"></div>

            <p class="text-center text-sm text-[var(--color-text)]">
                Already have an account?
                <a href="{{ route('customer.login', !empty($redirect) ? ['redirect' => $redirect] : []) }}" class="text-[var(--color-primary)] hover:underline">Sign in</a>
            </p>
        </div>
    </div>
    <x-storefront.region name="content_bottom" />
    </x-storefront.columns>
</x-store-layout>
