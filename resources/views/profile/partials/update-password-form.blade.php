<x-admin.form :action="route('password.update')" method="PUT">
    <x-admin.panel title="Update Password">
        <div class="v-grid v-grid--2">
            {{-- Errors ride the updatePassword bag, so the spans are
                 explicit rather than x-admin.field's default-bag lookup. --}}
            <label class="v-field">
                <span class="v-field__label">Current password <span class="v-req">Required</span></span>
                <input type="password" name="current_password" autocomplete="current-password" class="v-control">
                @error('current_password', 'updatePassword')
                    <span class="v-field__error">{{ $message }}</span>
                @enderror
            </label>
            <span></span>
            <label class="v-field">
                <span class="v-field__label">New password <span class="v-req">Required</span></span>
                <input type="password" name="password" autocomplete="new-password" class="v-control">
                @error('password', 'updatePassword')
                    <span class="v-field__error">{{ $message }}</span>
                @enderror
            </label>
            <label class="v-field">
                <span class="v-field__label">Confirm password <span class="v-req">Required</span></span>
                <input type="password" name="password_confirmation" autocomplete="new-password" class="v-control">
                @error('password_confirmation', 'updatePassword')
                    <span class="v-field__error">{{ $message }}</span>
                @enderror
            </label>
        </div>
        @if (session('status') === 'password-updated')
            <p class="v-cell-quiet v-gap-sm" x-data="{ show: true }" x-show="show" x-transition
               x-init="setTimeout(() => show = false, 2000)" role="status">Saved.</p>
        @endif
    </x-admin.panel>
</x-admin.form>
