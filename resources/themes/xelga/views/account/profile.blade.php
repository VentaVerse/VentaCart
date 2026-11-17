{{--
    Profile and password: core's two forms. The name and the email
    preference save through account.profile.update; the password through
    account.password.update, which checks the current one first. The email
    itself cannot be changed here.
--}}
@php app(\Themes\Xelga\Rendering\Storefront::class)->page('account'); @endphp
<x-store-layout title="Profile and password">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="profile">
        <div class="s__inner">
            @include('xelga::partials.fixed.crumbs', ['trail' => [['label' => 'Your account', 'url' => route('account.dashboard')], ['label' => 'Profile and password']]])
            <div class="acct">
                @include('xelga::partials.fixed.account-nav')
                <div class="acct__main">
                    <header class="acct__head"><h1 class="acct__title">Profile and password</h1></header>
                    @include('xelga::partials.fixed.flash')
                    <section class="acct__block acct__card" aria-labelledby="details-title">
                        <h2 class="acct__subtitle" id="details-title">Your details</h2>
                        <form class="acct__form" method="POST" action="{{ route('account.profile.update') }}">
                            @csrf
                            <div class="form-grid">
                                <div class="field">
                                    <label class="field__label" for="pr-first">First name</label>
                                    <input class="input" id="pr-first" type="text" name="first_name" value="{{ old('first_name', $customer->first_name) }}" autocomplete="given-name" required @error('first_name') aria-invalid="true" aria-describedby="pr-first-error" @enderror>
                                    @error('first_name')<p class="field__error" id="pr-first-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="field">
                                    <label class="field__label" for="pr-last">Last name</label>
                                    <input class="input" id="pr-last" type="text" name="last_name" value="{{ old('last_name', $customer->last_name) }}" autocomplete="family-name" required @error('last_name') aria-invalid="true" aria-describedby="pr-last-error" @enderror>
                                    @error('last_name')<p class="field__error" id="pr-last-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="field field--full">
                                    <label class="field__label" for="pr-email">Email</label>
                                    <input class="input" id="pr-email" type="email" value="{{ $customer->email }}" disabled aria-describedby="pr-email-help">
                                    <p class="field__help" id="pr-email-help">Your email cannot be changed here.</p>
                                </div>
                            </div>
                            {{-- The customer's own email preference, kept so saving the profile never changes it unasked. --}}
                            <label class="check"><input type="checkbox" name="newsletter" value="1" @checked(old('newsletter', $customer->newsletter))><span>Email me store news and offers</span></label>
                            <button type="submit" class="btn">Save changes</button>
                        </form>
                    </section>
                    <section class="acct__block acct__card" aria-labelledby="password-title">
                        <h2 class="acct__subtitle" id="password-title">Change your password</h2>
                        <form class="acct__form" method="POST" action="{{ route('account.password.update') }}">
                            @csrf
                            <div class="field">
                                <label class="field__label" for="pw-current">Current password</label>
                                <div class="password">
                                    <input class="input" id="pw-current" type="password" name="current_password" autocomplete="current-password" required @error('current_password') aria-invalid="true" aria-describedby="pw-current-error" @enderror>
                                    <button type="button" class="icon-btn password__toggle" data-password-toggle aria-controls="pw-current" aria-pressed="false" aria-label="Show password" hidden><svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg></button>
                                </div>
                                @error('current_password')<p class="field__error" id="pw-current-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="form-grid">
                                <div class="field">
                                    <label class="field__label" for="pw-new">New password</label>
                                    <input class="input" id="pw-new" type="password" name="password" autocomplete="new-password" minlength="8" required aria-describedby="pw-new-help @error('password') pw-new-error @enderror" @error('password') aria-invalid="true" @enderror>
                                    <p class="field__help" id="pw-new-help">At least 8 characters.</p>
                                    @error('password')<p class="field__error" id="pw-new-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="field">
                                    <label class="field__label" for="pw-new2">Type the new password again</label>
                                    <input class="input" id="pw-new2" type="password" name="password_confirmation" autocomplete="new-password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn">Update password</button>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-store-layout>
