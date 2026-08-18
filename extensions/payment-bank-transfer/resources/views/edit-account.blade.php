<x-admin.shell :title="'Edit ' . $bankAccount->bank_name">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.payment-settings.show', 'bank_transfer') }}">Bank Transfer</a>
                <span aria-hidden="true">/</span>
                <span>{{ $bankAccount->bank_name }}</span>
            </div>
            <h1 class="v-head__title">{{ $bankAccount->bank_name }}</h1>
        </div>
    </div>

    <x-admin.form :action="route('admin.bank-accounts.update', $bankAccount)" method="PUT" enctype="multipart/form-data" class="v-settle">
        <x-admin.panel title="Account">
            <div class="v-grid v-grid--2">
                <x-admin.field name="bank_name" label="Bank name" required>
                    <input type="text" name="bank_name" required class="v-control"
                           value="{{ old('bank_name', $bankAccount->bank_name) }}">
                </x-admin.field>
                <x-admin.field name="account_name" label="Account name" required>
                    <input type="text" name="account_name" required class="v-control"
                           value="{{ old('account_name', $bankAccount->account_name) }}">
                </x-admin.field>
                <x-admin.field name="account_number" label="Account number" required>
                    <input type="text" name="account_number" required class="v-control v-mono"
                           value="{{ old('account_number', $bankAccount->account_number) }}">
                </x-admin.field>
                <x-admin.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v-control v-mono v-w-sort"
                           value="{{ old('sort_order', $bankAccount->sort_order) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="qr_image" label="QR code image" hint="Max 2MB. Shown to the customer after order placement.">
                    @if ($bankAccount->qr_image)
                        <div class="v-markrow v-gap-sm">
                            <img src="{{ storage_url($bankAccount->qr_image) }}" alt="QR code" class="v-qr v-qr--lg">
                            <label class="v-check">
                                <input type="checkbox" name="remove_qr" value="1">
                                Remove QR image
                            </label>
                        </div>
                    @endif
                    <input type="file" name="qr_image" accept="image/*" class="v-file">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="instructions" label="Instructions">
                    <textarea name="instructions" rows="3" class="v-control">{{ old('instructions', $bankAccount->instructions) }}</textarea>
                </x-admin.field>
            </div>
            <div class="v-gap">
                <label class="v-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $bankAccount->is_active))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Active
                </label>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
