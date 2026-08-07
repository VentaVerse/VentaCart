<x-admin.card title="Delete Account" description="Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.">
    <div x-data="{ open: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
        <x-admin.button type="button" variant="danger" @click="open = true">Delete Account</x-admin.button>

        <div x-show="open" x-cloak @keydown.escape.window="open = false"
             class="fixed inset-0 z-[90] flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
            <div x-show="open" x-transition
                 class="relative w-full max-w-sm bg-v-surface border border-v-border rounded-v-lg shadow-2xl"
                 role="dialog" aria-modal="true" aria-label="Delete account">
                <form method="post" action="{{ route('admin.profile.destroy') }}" data-v-form class="p-5">
                    @csrf
                    @method('delete')

                    <h2 class="text-[15px] font-semibold text-v-text">Are you sure you want to delete your account?</h2>
                    <p class="mt-1.5 text-[13px] text-v-secondary">
                        Once your account is deleted, all of its resources and data will be permanently deleted.
                        Please enter your password to confirm you would like to permanently delete your account.
                    </p>

                    <div class="mt-4">
                        <label for="delete_account_password" class="sr-only">Password</label>
                        {{-- Raw input (not x-admin.input) to avoid an id="password" collision with the
                             Update Password form's own "password" field, which lives on this same page. --}}
                        <input type="password" name="password" id="delete_account_password" placeholder="Password" autocomplete="current-password"
                            class="v-control w-full h-[var(--control-h)] px-3 text-[13px] bg-v-surface text-v-text border rounded-v-md placeholder:text-v-muted {{ $errors->userDeletion->has('password') ? 'border-v-danger' : 'border-v-border' }}">
                        @error('password', 'userDeletion')
                            <p class="mt-1.5 text-xs text-v-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <x-admin.button type="button" variant="secondary" @click="open = false">Cancel</x-admin.button>
                        <x-admin.button type="submit" variant="danger">Delete Account</x-admin.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin.card>
