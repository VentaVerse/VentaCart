<x-admin.auth-shell title="Sign in">
    <form method="POST" action="{{ route('login') }}" data-v-form class="v3-authform">
        @csrf

        <x-admin3.field name="username" label="Username" required>
            <input type="text" name="username" class="v3-control" value="{{ old('username') }}" required autofocus autocomplete="username" />
        </x-admin3.field>

        <x-admin3.field name="password" label="Password" required>
            <input type="password" name="password" class="v3-control" required autocomplete="current-password" />
        </x-admin3.field>

        <div class="v3-between">
            <label class="v3-check"><input type="checkbox" name="remember" value="1"><span>Remember me</span></label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-[13px] text-v-secondary hover:text-v-text">Forgot password?</a>
            @endif
        </div>

        <button type="submit" class="v3-btn v3-btn--primary v3-block-btn">Sign in</button>
    </form>
</x-admin.auth-shell>
