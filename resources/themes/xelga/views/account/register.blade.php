{{--
    Create an account. Core's form and checks: first and last name, email,
    password typed twice, the captcha when the store uses one, and the
    redirect back to checkout.
--}}
@php app(\Themes\Xelga\Rendering\Storefront::class)->page('auth'); @endphp
<x-store-layout title="Create an account">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="register">
        <div class="s__inner">
            <div class="auth">
                <section class="auth__panel" aria-labelledby="register-title">
                    <h1 class="auth__title" id="register-title">Create an account</h1>
                    <p class="auth__text">An account is how you check out with us. It keeps your addresses, orders and tracking numbers in one place.</p>
                    @include('xelga::partials.fixed.flash', ['showErrors' => true])
                    <form class="auth__form" method="POST" action="{{ route('customer.register.submit') }}">
                        @csrf
                        @if (! empty($redirect))
                            <input type="hidden" name="redirect" value="{{ $redirect }}">
                        @endif
                        <div class="form-grid">
                            <div class="field">
                                <label class="field__label" for="r-first">First name</label>
                                <input class="input" id="r-first" name="first_name" type="text" value="{{ old('first_name') }}" autocomplete="given-name" required @error('first_name') aria-invalid="true" @enderror>
                            </div>
                            <div class="field">
                                <label class="field__label" for="r-last">Last name</label>
                                <input class="input" id="r-last" name="last_name" type="text" value="{{ old('last_name') }}" autocomplete="family-name" required @error('last_name') aria-invalid="true" @enderror>
                            </div>
                        </div>
                        <div class="field">
                            <label class="field__label" for="r-email">Email</label>
                            <input class="input" id="r-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required @error('email') aria-invalid="true" @enderror>
                        </div>
                        <div class="field">
                            <label class="field__label" for="r-password">Password</label>
                            <div class="password">
                                <input class="input" id="r-password" name="password" type="password" autocomplete="new-password" minlength="8" required aria-describedby="r-password-help" @error('password') aria-invalid="true" @enderror>
                                <button type="button" class="icon-btn password__toggle" data-password-toggle aria-controls="r-password" aria-pressed="false" aria-label="Show password" hidden><svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg></button>
                            </div>
                            <p class="field__help" id="r-password-help">At least 8 characters.</p>
                        </div>
                        <div class="field">
                            <label class="field__label" for="r-password2">Type the password again</label>
                            <input class="input" id="r-password2" name="password_confirmation" type="password" autocomplete="new-password" required>
                        </div>
                        <x-turnstile />
                        <button type="submit" class="btn btn--block">Create my account</button>
                    </form>
                </section>
                <section class="auth__panel auth__panel--alt" aria-labelledby="signin-title">
                    <h2 class="auth__title" id="signin-title">Already have an account?</h2>
                    <p class="auth__text">Sign in to check out with your saved addresses and see your orders.</p>
                    <a class="btn btn--secondary btn--block" href="{{ route('customer.login', ! empty($redirect) ? ['redirect' => $redirect] : []) }}">Sign in</a>
                </section>
            </div>
        </div>
    </div>
</x-store-layout>
