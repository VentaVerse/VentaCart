<x-admin.shell title="My Profile">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">My Profile</h1>
        </div>
    </div>

    <div class="v-settle">
        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')
        @include('profile.partials.delete-user-form')
    </div>
</x-admin.shell>
