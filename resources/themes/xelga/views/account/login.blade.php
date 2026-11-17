{{--
    Sign in. Core's form, fields and checks: email, password, remember me,
    the captcha when the store uses one, and the redirect back to checkout.
    An account moved from an earlier store has no password yet; core then
    asks for a new one, and this page offers to email the reset link.
--}}
@php app(\Themes\Xelga\Rendering\Storefront::class)->page('auth'); @endphp
<x-store-layout title="Sign in">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="login">
        <div class="s__inner">
            <div class="auth">
                <section class="auth__panel" aria-labelledby="login-title">
                    <h1 class="auth__title" id="login-title">Sign in</h1>
                    <p class="auth__text">Welcome back. Sign in to check out, see your orders and keep your wishlist.</p>
                    @include('xelga::partials.fixed.flash')
                    @if ($errors->has('email'))
                        <div class="form-status form-status--error" role="alert"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-alert"></use></svg><span>{{ $errors->first('email') }}</span></div>
                    @endif
                    @if ($errors->has('needs_reset'))
                        <div class="form-status form-status--info auth__reset" role="alert" data-migration-reset data-url="{{ route('customer.password.migration') }}" data-email="{{ $errors->first('needs_reset') }}">
                            <p><strong>Set a new password to continue.</strong> Your account was moved to this store, and for your security it needs a new password. We send a reset link to <strong>{{ $errors->first('needs_reset') }}</strong>; check your spam folder if it does not arrive.</p>
                            <p class="auth__reset-acts">
                                <button type="button" class="btn btn--sm" data-migration-send>Send the reset link</button>
                                <a class="text-btn" href="{{ route('customer.password.request') }}">Use the password reset page</a>
                            </p>
                            <p class="auth__reset-msg" data-migration-msg role="status"></p>
                        </div>
                    @endif
                    <form class="auth__form" method="POST" action="{{ route('customer.login.submit') }}">
                        @csrf
                        @if (! empty($redirect))
                            <input type="hidden" name="redirect" value="{{ $redirect }}">
                        @endif
                        <div class="field">
                            <label class="field__label" for="l-email">Email</label>
                            <input class="input" id="l-email" name="email" type="email" value="{{ old('email', $errors->first('needs_reset')) }}" autocomplete="email" required autofocus @if ($errors->has('email')) aria-invalid="true" @endif>
                        </div>
                        <div class="field">
                            <div class="field__row"><label class="field__label" for="l-password">Password</label><a class="text-btn" href="{{ route('customer.password.request') }}">Forgot your password?</a></div>
                            <div class="password">
                                <input class="input" id="l-password" name="password" type="password" autocomplete="current-password" required>
                                <button type="button" class="icon-btn password__toggle" data-password-toggle aria-controls="l-password" aria-pressed="false" aria-label="Show password" hidden><svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg></button>
                            </div>
                        </div>
                        <label class="check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Keep me signed in on this device</span></label>
                        <x-turnstile />
                        <button type="submit" class="btn btn--block">Sign in</button>
                    </form>
                </section>
                <section class="auth__panel auth__panel--alt" aria-labelledby="register-title">
                    <h2 class="auth__title" id="register-title">New here?</h2>
                    <p class="auth__text">An account is how you check out with us. It also keeps your addresses, order history and tracking numbers in one place.</p>
                    <ul class="auth__perks">
                        <li><svg class="icon icon--sm" aria-hidden="true"><use href="#i-truck"></use></svg>Track every parcel from your account</li>
                        <li><svg class="icon icon--sm" aria-hidden="true"><use href="#i-heart"></use></svg>Save products to your wishlist</li>
                        <li><svg class="icon icon--sm" aria-hidden="true"><use href="#i-map-pin"></use></svg>Keep delivery addresses for next time</li>
                    </ul>
                    <a class="btn btn--secondary btn--block" href="{{ route('customer.register', ! empty($redirect) ? ['redirect' => $redirect] : []) }}">Create an account</a>
                </section>
            </div>
        </div>
    </div>
</x-store-layout>
