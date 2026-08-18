<x-admin3.panel title="Delete Account"
    note="Deleting the account permanently removes it and everything it owns. Download anything worth keeping first.">
    <div class="v3-passthru" x-data="{ open: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }"
         @keydown.escape.window="open = false">
        <button type="button" class="v3-btn v3-btn--danger" @click="open = true">Delete account</button>

        <template x-if="open">
            <div class="v3-overlay" @click.self="open = false">
                <div class="v3-modal" role="dialog" aria-modal="true" aria-label="Delete account">
                    <form method="post" action="{{ route('admin.profile.destroy') }}">
                        @csrf
                        @method('delete')

                        <h2 class="v3-modal__title">Are you sure you want to delete your account?</h2>
                        <p class="v3-modal__body">
                            Once your account is deleted, all of its resources and data will be permanently deleted.
                            Please enter your password to confirm you would like to permanently delete your account.
                        </p>

                        <label class="v3-field v3-gap-sm">
                            <span class="v3-field__label">Password</span>
                            {{-- id kept distinct from the Update Password form's own
                                 "password" field, which lives on this same page. --}}
                            <input type="password" name="password" id="delete_account_password" autocomplete="current-password"
                                   class="v3-control {{ $errors->userDeletion->has('password') ? 'is-invalid' : '' }}">
                            @error('password', 'userDeletion')
                                <span class="v3-field__error">{{ $message }}</span>
                            @enderror
                        </label>

                        <div class="v3-modal__acts">
                            <button type="button" class="v3-btn v3-btn--ghost" @click="open = false">Cancel</button>
                            <button type="submit" class="v3-btn v3-btn--danger-fill">Delete account</button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-admin3.panel>
