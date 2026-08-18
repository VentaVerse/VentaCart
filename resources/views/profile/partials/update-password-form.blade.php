<x-admin3.form :action="route('password.update')" method="PUT">
    <x-admin3.panel title="Update Password">
        <div class="v3-grid v3-grid--2">
            {{-- Errors ride the updatePassword bag, so the spans are
                 explicit rather than x-admin3.field's default-bag lookup. --}}
            <label class="v3-field">
                <span class="v3-field__label">Current password <span class="v3-req">Required</span></span>
                <input type="password" name="current_password" autocomplete="current-password" class="v3-control">
                @error('current_password', 'updatePassword')
                    <span class="v3-field__error">{{ $message }}</span>
                @enderror
            </label>
            <span></span>
            <label class="v3-field">
                <span class="v3-field__label">New password <span class="v3-req">Required</span></span>
                <input type="password" name="password" autocomplete="new-password" class="v3-control">
                @error('password', 'updatePassword')
                    <span class="v3-field__error">{{ $message }}</span>
                @enderror
            </label>
            <label class="v3-field">
                <span class="v3-field__label">Confirm password <span class="v3-req">Required</span></span>
                <input type="password" name="password_confirmation" autocomplete="new-password" class="v3-control">
                @error('password_confirmation', 'updatePassword')
                    <span class="v3-field__error">{{ $message }}</span>
                @enderror
            </label>
        </div>
        @if (session('status') === 'password-updated')
            <p class="v3-cell-quiet v3-gap-sm" x-data="{ show: true }" x-show="show" x-transition
               x-init="setTimeout(() => show = false, 2000)" role="status">Saved.</p>
        @endif
    </x-admin3.panel>
</x-admin3.form>
