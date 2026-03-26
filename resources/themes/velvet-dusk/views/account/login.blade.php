<x-store-layout title="Sign In">
    <div class="container-store py-16">
        <div class="account-auth-card" x-data="loginForm()">
            <h1 class="account-auth-title text-center">SIGN IN</h1>

            @if (session('status'))
                <div class="account-flash-success mb-6">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->has('email'))
                <div class="account-flash-error mb-6">
                    {{ $errors->first('email') }}
                </div>
            @endif

            <form method="POST" action="{{ route('customer.login.submit') }}" @submit.prevent="submitLogin($event)">
                @csrf
                @if (!empty($redirect))
                    <input type="hidden" name="redirect" value="{{ $redirect }}">
                @endif

                <div class="space-y-4">
                    <div>
                        <label class="account-label">Email Address</label>
                        <input type="email" name="email" x-model="email" value="{{ old('email') }}" required autofocus
                               class="account-input w-full">
                    </div>

                    <div>
                        <label class="account-label">Password</label>
                        <input type="password" name="password" required
                               class="account-input w-full">
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-[#9A8575] cursor-pointer">
                            <input type="checkbox" name="remember" class="accent-[var(--color-primary)]">
                            Remember me
                        </label>
                        <a href="{{ route('customer.password.request') }}" class="text-sm text-[var(--color-primary)] hover:underline">Forgot password?</a>
                    </div>

                    <x-turnstile />

                    <button type="submit" class="account-btn-primary w-full">SIGN IN</button>
                </div>
            </form>

            <div class="divider-glow my-6"></div>

            <p class="text-center text-sm text-[#9A8575]">
                Don't have an account?
                <a href="{{ route('customer.register', !empty($redirect) ? ['redirect' => $redirect] : []) }}" class="text-[var(--color-primary)] hover:underline">Create one</a>
            </p>

            {{-- Migration Reset Modal --}}
            <template x-teleport="body">
                <div x-show="showResetModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.6)">
                    <div @click.outside="showResetModal = false" x-show="showResetModal" x-transition.scale
                         class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 relative">
                        <button @click="showResetModal = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>

                        <div x-show="!resetSent">
                            <div class="text-center mb-4">
                                <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                                </div>
                                <h2 class="text-lg font-semibold text-gray-900">Welcome to our new platform!</h2>
                                <p class="text-sm text-gray-600 mt-2">We've upgraded our store to a brand new system. For your security, you'll need to set a new password.</p>
                                <p class="text-sm text-gray-600 mt-1">We'll send a password reset link to <strong x-text="email"></strong>.</p>
                                <p class="text-sm text-gray-600 mt-2 font-semibold">Please check your spam or junk folder if you don't see it in your inbox.</p>
                            </div>
                            <button @click="sendResetLink()" :disabled="resetLoading"
                                class="w-full py-2.5 rounded-lg text-sm font-medium text-white transition-colors"
                                :class="resetLoading ? 'bg-gray-400' : 'bg-[var(--color-primary)] hover:opacity-90'">
                                <span x-show="!resetLoading">Send Password Reset Link</span>
                                <span x-show="resetLoading">Sending...</span>
                            </button>
                            <p x-show="resetError" x-text="resetError" class="text-sm text-red-600 text-center mt-2"></p>
                        </div>

                        <div x-show="resetSent" class="text-center">
                            <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h2 class="text-lg font-semibold text-gray-900">Check your email!</h2>
                            <p class="text-sm text-gray-600 mt-2">A password reset link has been sent to <strong x-text="email"></strong>. Click the link in the email to set your new password.</p>
                            <button @click="showResetModal = false" class="mt-4 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Close</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    @push('scripts')
    <script>
    function loginForm() {
        return {
            email: '{{ old("email", "") }}',
            showResetModal: {{ $errors->has('needs_reset') ? 'true' : 'false' }},
            resetSent: false,
            resetLoading: false,
            resetError: '',

            init() {
                @if ($errors->has('needs_reset'))
                    this.email = '{{ $errors->first("needs_reset") }}';
                @endif
            },

            submitLogin(e) {
                e.target.submit();
            },

            async sendResetLink() {
                this.resetLoading = true;
                this.resetError = '';
                try {
                    const res = await fetch('{{ route("customer.password.migration") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ email: this.email })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.resetSent = true;
                    } else {
                        this.resetError = data.message || 'Something went wrong.';
                    }
                } catch (e) {
                    this.resetError = 'Something went wrong. Please try again.';
                }
                this.resetLoading = false;
            }
        };
    }
    </script>
    @endpush
</x-store-layout>
