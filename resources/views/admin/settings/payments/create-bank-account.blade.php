<x-admin3.shell title="Add bank account">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.payment-settings.index') }}">Payments</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.payment-settings.show', 'bank_transfer') }}">Bank Transfer</a>
                <span aria-hidden="true">/</span>
                <span>Add account</span>
            </div>
            <h1 class="v3-head__title">Add bank account</h1>
        </div>
    </div>

    <x-admin3.form :action="route('admin.bank-accounts.store')" enctype="multipart/form-data" class="v3-settle">
        <x-admin3.panel title="Account">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="bank_name" label="Bank name" required>
                    <input type="text" name="bank_name" required class="v3-control" value="{{ old('bank_name') }}">
                </x-admin3.field>
                <x-admin3.field name="account_name" label="Account name" required>
                    <input type="text" name="account_name" required class="v3-control" value="{{ old('account_name') }}">
                </x-admin3.field>
                <x-admin3.field name="account_number" label="Account number" required>
                    <input type="text" name="account_number" required class="v3-control v3-mono" value="{{ old('account_number') }}">
                </x-admin3.field>
                <x-admin3.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v3-control v3-mono v3-w-sort" value="{{ old('sort_order', 0) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="qr_image" label="QR code image" hint="Max 2MB. Shown to the customer after order placement.">
                    <input type="file" name="qr_image" accept="image/*" class="v3-file">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="instructions" label="Instructions">
                    <textarea name="instructions" rows="3" class="v3-control">{{ old('instructions') }}</textarea>
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <label class="v3-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Active
                </label>
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.shell>
