<x-admin.shell title="My Profile">
    <x-admin.page title="My Profile" :breadcrumb="[['label' => 'My Profile']]">
        <div class="max-w-2xl space-y-6">
            @include('profile.partials.update-profile-information-form')
            @include('profile.partials.update-password-form')
            @include('profile.partials.delete-user-form')
        </div>
    </x-admin.page>
</x-admin.shell>
