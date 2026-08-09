<x-admin.card title="Profile Information">
    <form method="post" action="{{ route('admin.profile.update') }}" data-v-form class="space-y-5">
        @csrf
        @method('patch')

        <x-admin.field name="name" label="Name" required>
            <x-admin.input name="name" :value="$user->name" required autofocus autocomplete="name" />
        </x-admin.field>

        <x-admin.field name="email" label="Email" required>
            <x-admin.input name="email" type="email" :value="$user->email" required autocomplete="username" />
        </x-admin.field>

        <x-admin.form-actions>
            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-[13px] text-v-muted"
                >Saved.</p>
            @endif
            <x-admin.button type="submit">Save</x-admin.button>
        </x-admin.form-actions>
    </form>
</x-admin.card>
