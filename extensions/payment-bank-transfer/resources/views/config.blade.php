<x-admin3.form :action="route('admin.settings.update')" id="pay-bt-form">
    <input type="hidden" name="group" value="payment">
    <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'bank_transfer') }}">

    <x-admin3.panel title="Bank Transfer">
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
    <x-slot:acts>
        <a href="{{ route('admin.bank-accounts.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Add account</a>
    </x-slot:acts>
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
