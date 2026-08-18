<x-admin.auth-shell title="Confirm password" subtitle="This is a secure area of the application. Please confirm your password before continuing.">
    <form method="POST" action="{{ route('password.confirm') }}" data-v-form class="v3-authform">
        @csrf

        <x-admin3.field name="password" label="Password" required>
            <input type="password" name="password" class="v3-control" required autofocus autocomplete="current-password" />
        </x-admin3.field>

        <button type="submit" class="v3-btn v3-btn--primary v3-block-btn">Confirm</button>
    </form>
</x-admin.auth-shell>
