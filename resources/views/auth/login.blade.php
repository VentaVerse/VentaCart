<x-admin.auth-shell title="Sign in">
    <form method="POST" action="{{ route('login') }}" data-v-form class="v-authform">
        @csrf

        <x-admin.field name="username" label="Username" required>
            <input type="text" name="username" class="v-control" value="{{ old('username') }}" required autofocus autocomplete="username" />
        </x-admin.field>

        <x-admin.field name="password" label="Password" required>
            <input type="password" name="password" class="v-control" required autocomplete="current-password" />
        </x-admin.field>

        <div class="v-between">
            <label class="v-check"><input type="checkbox" name="remember" value="1"><span>Remember me</span></label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-[13px] text-v-secondary hover:text-v-text">Forgot password?</a>
            @endif
        </div>

        <button type="submit" class="v-btn v-btn--primary v-block-btn">Sign in</button>
    </form>
</x-admin.auth-shell>
