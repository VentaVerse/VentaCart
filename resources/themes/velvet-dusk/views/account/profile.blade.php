<x-store-layout title="Profile Settings">
    <div class="container-store py-10">
        <h1 class="section-title reveal">PROFILE SETTINGS</h1>

        <div class="account-layout">
            @include('account.partials.sidebar')

            <div class="space-y-6">
                @if (session('success'))
                    <div class="account-flash-success reveal">{{ session('success') }}</div>
                @endif

                {{-- Account Details --}}
                <div class="account-card reveal">
                    <h2 class="account-section-title">Account Details</h2>

                    <form method="POST" action="{{ route('account.profile.update') }}">
                        @csrf
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="account-label">First Name</label>
                                    <input type="text" name="first_name" value="{{ old('first_name', $customer->first_name) }}" required class="account-input w-full">
                                    @error('first_name') <p class="text-xs text-[var(--color-secondary)] mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="account-label">Last Name</label>
                                    <input type="text" name="last_name" value="{{ old('last_name', $customer->last_name) }}" required class="account-input w-full">
                                    @error('last_name') <p class="text-xs text-[var(--color-secondary)] mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="account-label">Email</label>
                                <input type="email" value="{{ $customer->email }}" disabled class="account-input w-full opacity-50 cursor-not-allowed">
                                <p class="text-xs text-[#826F66] mt-1">Email cannot be changed.</p>
                            </div>

                            <label class="flex items-center gap-2 text-sm text-[#9A8575] cursor-pointer">
                                <input type="checkbox" name="newsletter" value="1" {{ $customer->newsletter ? 'checked' : '' }} class="accent-[var(--color-primary)]">
                                Subscribe to newsletter
                            </label>

                            <button type="submit" class="account-btn-primary">SAVE CHANGES</button>
                        </div>
                    </form>
                </div>

                {{-- Change Password --}}
                <div class="account-card reveal">
                    <h2 class="account-section-title">Change Password</h2>

                    <form method="POST" action="{{ route('account.password.update') }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label class="account-label">Current Password</label>
                                <input type="password" name="current_password" required class="account-input w-full">
                                @error('current_password') <p class="text-xs text-[var(--color-secondary)] mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="account-label">New Password</label>
                                <input type="password" name="password" required class="account-input w-full">
                                @error('password') <p class="text-xs text-[var(--color-secondary)] mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="account-label">Confirm New Password</label>
                                <input type="password" name="password_confirmation" required class="account-input w-full">
                            </div>

                            <button type="submit" class="account-btn-primary">UPDATE PASSWORD</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-store-layout>
