<x-admin.auth-shell title="Confirm password" subtitle="This is a secure area of the application. Please confirm your password before continuing.">
    <form method="POST" action="{{ route('password.confirm') }}" data-v-form class="space-y-5">
        @csrf

        <x-admin.field name="password" label="Password" required>
            <x-admin.input type="password" name="password" required autofocus autocomplete="current-password" />
        </x-admin.field>

        <x-admin.button type="submit" class="w-full">Confirm</x-admin.button>
    </form>
</x-admin.auth-shell>
