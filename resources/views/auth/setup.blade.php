<x-admin.auth-shell title="Create your admin account">
    <form method="POST" action="{{ route('setup.store') }}" data-v-form class="v-authform">
        @csrf

        <x-admin.field name="email" label="Email" required hint="Where password reset links are sent.">
            <input type="email" name="email" class="v-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus autocomplete="email" />
        </x-admin.field>

        <x-admin.field name="username" label="Username" required hint="You sign in with this. Letters, numbers, dashes and underscores.">
            <input type="text" name="username" class="v-control @error('username') is-invalid @enderror" value="{{ old('username') }}" required autocomplete="username" />
        </x-admin.field>

        <x-admin.field name="password" label="Password" required hint="At least 8 characters.">
            <input type="password" name="password" class="v-control @error('password') is-invalid @enderror" required autocomplete="new-password" />
        </x-admin.field>

        <x-admin.field name="password_confirmation" label="Confirm password" required>
            <input type="password" name="password_confirmation" class="v-control" required autocomplete="new-password" />
        </x-admin.field>

        <button type="submit" class="v-btn v-btn--primary v-block-btn">Create account and sign in</button>
    </form>
</x-admin.auth-shell>
