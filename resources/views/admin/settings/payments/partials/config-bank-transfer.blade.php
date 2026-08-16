<x-admin3.form :action="route('admin.settings.update')" id="pay-bt-form">
    <input type="hidden" name="group" value="payment">
    <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'bank_transfer') }}">

    <x-admin3.panel title="Bank Transfer">
        <x-slot:acts>
            <label class="v3-toggle">
                <input type="hidden" name="settings[bank_transfer_enabled]" value="0" form="pay-bt-form">
                <input type="checkbox" name="settings[bank_transfer_enabled]" value="1" form="pay-bt-form"
                       @checked(($settings['payment']['bank_transfer_enabled'] ?? '0') === '1') aria-label="Enabled">
                <span class="v3-toggle__track" aria-hidden="true"></span>
            </label>
        </x-slot:acts>
        <div class="v3-narrow">
            <x-admin3.field name="settings.bank_transfer_display_name" label="Checkout label"
                hint="The bank name (e.g. BDO) is appended automatically per account. Blank uses the default.">
                <input type="text" name="settings[bank_transfer_display_name]" class="v3-control" placeholder="{{ $defaultName }}"
                       value="{{ $settings['payment']['bank_transfer_display_name'] ?? '' }}">
            </x-admin3.field>
            <div class="v3-gap">
                <x-admin3.field name="settings.bank_transfer_success_status_id" label="Order status on checkout">
                    <select name="settings[bank_transfer_success_status_id]" class="v3-control">
                        <option value="">Default (Pending)</option>
                        @foreach ($orderStatuses as $os)
                            <option value="{{ $os->id }}" @selected(($settings['payment']['bank_transfer_success_status_id'] ?? '') == $os->id)>{{ $os->name }}</option>
                        @endforeach
                    </select>
                </x-admin3.field>
            </div>
        </div>
    </x-admin3.panel>
</x-admin3.form>


<x-admin3.panel title="Bank accounts" flush>
    <div class="v3-tablewrap">
        <table class="v3-table">
            <thead>
                <tr><th>QR</th><th>Bank</th><th>Account</th><th>Number</th><th>Status</th><th class="v3-cell-act"><span class="v3-sr">Actions</span></th></tr>
            </thead>
            <tbody>
                @forelse ($bankAccounts as $acct)
                    <tr>
                        <td>
                            @if ($acct->qr_image)
                                <img src="{{ storage_url($acct->qr_image) }}" alt="QR code for {{ $acct->bank_name }}" class="v3-qr">
                            @else
                                <span class="v3-cell-quiet">-</span>
                            @endif
                        </td>
                        <td>{{ $acct->bank_name }}</td>
                        <td class="v3-cell-quiet">{{ $acct->account_name }}</td>
                        <td class="v3-mono">{{ $acct->account_number }}</td>
                        <td><span class="v3-badge {{ $acct->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $acct->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="v3-cell-act">
                            <a href="{{ route('admin.bank-accounts.edit', $acct) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                            <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-bank-account-{{ $acct->id }}')">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="v3-empty"><p class="v3-empty__title">No bank accounts yet</p></div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin3.panel>

@foreach ($bankAccounts as $acct)
    <x-admin3.confirm name="delete-bank-account-{{ $acct->id }}" title="Delete {{ $acct->bank_name }}?"
        message="{{ $acct->bank_name }} {{ $acct->account_number }} stops being offered at checkout and its QR code is deleted. Orders already paid to it keep their record."
        confirm="Delete" :action="route('admin.bank-accounts.destroy', $acct)" method="DELETE" />
@endforeach

<x-admin3.panel title="Add bank account">
    <form method="POST" action="{{ route('admin.bank-accounts.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="v3-grid v3-grid--2">
            <x-admin3.field name="bank_name" label="Bank name" required>
                <input type="text" name="bank_name" required class="v3-control">
            </x-admin3.field>
            <x-admin3.field name="account_name" label="Account name" required>
                <input type="text" name="account_name" required class="v3-control">
            </x-admin3.field>
            <x-admin3.field name="account_number" label="Account number" required>
                <input type="text" name="account_number" required class="v3-control v3-mono">
            </x-admin3.field>
            <x-admin3.field name="sort_order" label="Sort order">
                <input type="number" name="sort_order" min="0" value="0" class="v3-control v3-mono v3-w-sort">
            </x-admin3.field>
        </div>
        <div class="v3-gap">
            <x-admin3.field name="qr_image" label="QR code image" hint="Max 2MB. Shown to the customer after order placement.">
                <input type="file" name="qr_image" accept="image/*" class="v3-file">
            </x-admin3.field>
        </div>
        <div class="v3-gap">
            <x-admin3.field name="instructions" label="Instructions">
                <textarea name="instructions" rows="2" class="v3-control"></textarea>
            </x-admin3.field>
        </div>
        <div class="v3-gap v3-actrow">
            <label class="v3-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" checked>
                Active
            </label>
            <button type="submit" class="v3-btn v3-btn--primary">Add account</button>
        </div>
    </form>
</x-admin3.panel>
