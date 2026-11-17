{{-- Choose a new password from the emailed link: core's token, email and password typed twice. --}}
@php app(\Themes\Xelga\Rendering\Storefront::class)->page('auth'); @endphp
<x-store-layout title="Choose a new password">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="reset-password">
        <div class="s__inner">
            <div class="auth auth--single">
                <section class="auth__panel" aria-labelledby="reset-title">
                    <h1 class="auth__title" id="reset-title">Choose a new password</h1>
                    @include('xelga::partials.fixed.flash', ['showErrors' => true])
                    <form class="auth__form" method="POST" action="{{ route('customer.password.update') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="field">
                            <label class="field__label" for="p-email">Email</label>
                            <input class="input" id="p-email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="p-password">New password</label>
                            <div class="password">
                                <input class="input" id="p-password" name="password" type="password" autocomplete="new-password" minlength="8" required aria-describedby="p-password-help">
                                <button type="button" class="icon-btn password__toggle" data-password-toggle aria-controls="p-password" aria-pressed="false" aria-label="Show password" hidden><svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg></button>
                            </div>
                            <p class="field__help" id="p-password-help">At least 8 characters.</p>
                        </div>
                        <div class="field">
                            <label class="field__label" for="p-password2">Type the new password again</label>
                            <input class="input" id="p-password2" name="password_confirmation" type="password" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn--block">Save the new password</button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</x-store-layout>
