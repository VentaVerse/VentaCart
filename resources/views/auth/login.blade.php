<x-admin.auth-shell title="Sign in">
    <form method="POST" action="{{ route('login') }}" data-v-form class="space-y-5">
        @csrf

        <x-admin.field name="username" label="Username" required>
            <x-admin.input type="text" name="username" :value="old('username')" required autofocus autocomplete="username" placeholder="Enter your username" />
        </x-admin.field>

        <x-admin.field name="password" label="Password" required>
            <x-admin.input type="password" name="password" required autocomplete="current-password" placeholder="Enter password" />
        </x-admin.field>

        <div class="flex items-center justify-between">
            <x-admin.checkbox name="remember" label="Remember me" />
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-[13px] text-v-secondary hover:text-v-text">Forgot password?</a>
            @endif
        </div>

        <x-admin.button type="submit" class="w-full">Sign in</x-admin.button>
    </form>
</x-admin.auth-shell>
