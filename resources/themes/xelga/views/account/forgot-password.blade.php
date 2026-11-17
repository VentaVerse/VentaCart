{{-- Forgot password: core sends a reset link to the email, after the captcha when the store uses one. --}}
@php app(\Themes\Xelga\Rendering\Storefront::class)->page('auth'); @endphp
<x-store-layout title="Reset your password">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="forgot-password">
        <div class="s__inner">
            <div class="auth auth--single">
                <section class="auth__panel" aria-labelledby="forgot-title">
                    <h1 class="auth__title" id="forgot-title">Reset your password</h1>
                    <p class="auth__text">Enter the email you shop with. If it has an account, we send a link to choose a new password.</p>
                    @include('xelga::partials.fixed.flash', ['showErrors' => true])
                    <form class="auth__form" method="POST" action="{{ route('customer.password.email') }}">
                        @csrf
                        <div class="field">
                            <label class="field__label" for="f-email">Email</label>
                            <input class="input" id="f-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" @enderror>
                        </div>
                        <x-turnstile />
                        <button type="submit" class="btn btn--block">Send the reset link</button>
                        <a class="text-btn" href="{{ route('customer.login') }}"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-arrow-left"></use></svg>Back to sign in</a>
                    </form>
                </section>
            </div>
        </div>
    </div>
</x-store-layout>
