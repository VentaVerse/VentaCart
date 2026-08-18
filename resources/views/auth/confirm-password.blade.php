<x-admin.auth-shell title="Confirm password" subtitle="This is a secure area of the application. Please confirm your password before continuing.">
    <form method="POST" action="{{ route('password.confirm') }}" data-v-form class="v-authform">
        @csrf

        <x-admin.field name="password" label="Password" required>
            <input type="password" name="password" class="v-control" required autofocus autocomplete="current-password" />
        </x-admin.field>

        <button type="submit" class="v-btn v-btn--primary v-block-btn">Confirm</button>
    </form>
</x-admin.auth-shell>
