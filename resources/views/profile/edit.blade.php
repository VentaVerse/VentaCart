<x-admin3.shell title="My Profile">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">My Profile</h1>
        </div>
    </div>

    <div class="v3-settle">
        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')
        @include('profile.partials.delete-user-form')
    </div>
</x-admin3.shell>
