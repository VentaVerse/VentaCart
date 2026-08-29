<x-admin.form :header-save="false" :action="route('admin.settings.update')" id="pay-bt-form">
    <input type="hidden" name="group" value="payment">
    <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'bank_transfer') }}">

    <x-admin.panel title="Bank Transfer">
        <div class="v-narrow">
            <x-admin.field name="settings.bank_transfer_display_name" label="Checkout label"
                hint="The bank name (e.g. BDO) is appended automatically per account. Blank uses the default.">
                <input type="text" name="settings[bank_transfer_display_name]" class="v-control" placeholder="{{ $defaultName }}"
                       value="{{ $settings['payment']['bank_transfer_display_name'] ?? '' }}">
            </x-admin.field>
            <div class="v-gap">
                <x-admin.field name="settings.bank_transfer_success_status_id" label="Order status on checkout">
                    <select name="settings[bank_transfer_success_status_id]" class="v-control">
                        @foreach ($orderStatuses as $os)
                            <option value="{{ $os->id }}" @selected(($settings['payment']['bank_transfer_success_status_id'] ?? '') == $os->id)>{{ $os->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
            </div>
        </div>
    </x-admin.panel>
</x-admin.form>


<x-admin.panel title="Bank accounts" flush>
    <x-slot:acts>
        <a href="{{ route('admin.bank-accounts.create') }}" class="v-btn v-btn--primary v-btn--sm">Add account</a>
    </x-slot:acts>
    <div class="v-tablewrap">
        <table class="v-table">
            <thead>
                <tr><th>QR</th><th>Bank</th><th>Account</th><th>Number</th><th>Status</th><th class="v-cell-act"><span class="v-sr">Actions</span></th></tr>
            </thead>
            <tbody>
                @forelse ($bankAccounts as $acct)
                    <tr>
                        <td>
                            @if ($acct->qr_image)
                                <img src="{{ storage_url($acct->qr_image) }}" alt="QR code for {{ $acct->bank_name }}" class="v-qr">
                            @else
                                <span class="v-cell-quiet">-</span>
                            @endif
                        </td>
                        <td>{{ $acct->bank_name }}</td>
                        <td class="v-cell-quiet">{{ $acct->account_name }}</td>
                        <td class="v-mono">{{ $acct->account_number }}</td>
                        <td><span class="v-badge {{ $acct->is_active ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $acct->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="v-cell-act">
                            <a href="{{ route('admin.bank-accounts.edit', $acct) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                            <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-bank-account-{{ $acct->id }}')">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="v-empty"><p class="v-empty__title">No bank accounts yet</p></div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.panel>

@foreach ($bankAccounts as $acct)
    <x-admin.confirm name="delete-bank-account-{{ $acct->id }}" title="Delete {{ $acct->bank_name }}?"
        message="{{ $acct->bank_name }} {{ $acct->account_number }} stops being offered at checkout and its QR code is deleted. Orders already paid to it keep their record."
        confirm="Delete" :action="route('admin.bank-accounts.destroy', $acct)" method="DELETE" />
@endforeach
