<div class="space-y-6">
    <x-admin.card title="Bank Transfer">
        <x-slot name="headerActions">
            <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form>
                @csrf
                <input type="hidden" name="group" value="payment">
                <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'bank_transfer') }}">
                <x-admin.toggle name="settings[bank_transfer_enabled]" label="Enabled" lead
                    :checked="($settings['payment']['bank_transfer_enabled'] ?? '0') === '1'"
                    x-on:change="$el.closest('form').submit()" />
            </form>
        </x-slot>

        <form method="POST" action="{{ route('admin.settings.update') }}" data-v-form class="space-y-5">
            @csrf
            <input type="hidden" name="group" value="payment">
            <input type="hidden" name="_redirect" value="{{ route('admin.payment-settings.show', 'bank_transfer') }}">
            <x-admin.field name="settings[bank_transfer_display_name]" label="Checkout label"
                help="Shown at checkout. Bank name (e.g. “BDO”) is appended automatically per account. Leave blank for default.">
                <x-admin.input name="settings[bank_transfer_display_name]"
                    :value="$settings['payment']['bank_transfer_display_name'] ?? ''" placeholder="{{ $defaultName }}" />
            </x-admin.field>
            <x-admin.field name="settings[bank_transfer_success_status_id]" label="Order status on checkout">
                <x-admin.select name="settings[bank_transfer_success_status_id]" class="max-w-xs">
                    <option value="">Default (Pending)</option>
                    @foreach ($orderStatuses as $os)
                        <option value="{{ $os->id }}" @selected(($settings['payment']['bank_transfer_success_status_id'] ?? '') == $os->id)>{{ $os->name }}</option>
                    @endforeach
                </x-admin.select>
            </x-admin.field>
            <x-admin.form-actions>
                <x-admin.button variant="primary" type="submit">Save</x-admin.button>
            </x-admin.form-actions>
        </form>
    </x-admin.card>

    <x-admin.card title="Bank accounts">
        <x-admin.table>
            <x-slot name="head">
                <x-admin.table.th>QR</x-admin.table.th>
                <x-admin.table.th>Bank</x-admin.table.th>
                <x-admin.table.th>Account</x-admin.table.th>
                <x-admin.table.th>Number</x-admin.table.th>
                <x-admin.table.th>Status</x-admin.table.th>
                <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
            </x-slot>
            @forelse ($bankAccounts as $acct)
                <x-admin.table.row>
                    <x-admin.table.cell>
                        @if ($acct->qr_image)
                            <img src="{{ storage_url($acct->qr_image) }}" alt="QR code for {{ $acct->bank_name }}"
                                class="h-8 w-8 rounded object-contain border border-v-border">
                        @else
                            <span class="text-v-muted">-</span>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell>{{ $acct->bank_name }}</x-admin.table.cell>
                    <x-admin.table.cell>{{ $acct->account_name }}</x-admin.table.cell>
                    <x-admin.table.cell mono>{{ $acct->account_number }}</x-admin.table.cell>
                    <x-admin.table.cell>
                        <x-admin.badge :tone="$acct->is_active ? 'success' : 'neutral'">{{ $acct->is_active ? 'Active' : 'Inactive' }}</x-admin.badge>
                    </x-admin.table.cell>
                    <x-admin.table.cell class="v-col-act">
                        <div class="v-rowactions v-rowactions--end">
                            <x-admin.button size="sm" variant="ghost" :href="route('admin.bank-accounts.edit', $acct)">Edit</x-admin.button>
                            <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" type="button"
                                @click="$dispatch('open-modal', 'delete-bank-account-{{ $acct->id }}')">Delete</x-admin.button>
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="6"><x-admin.empty-state title="No bank accounts yet" /></td>
                </tr>
            @endforelse
        </x-admin.table>
    </x-admin.card>

    @foreach ($bankAccounts as $acct)
        <x-admin.confirm-dialog name="delete-bank-account-{{ $acct->id }}" title="Delete {{ $acct->bank_name }}?"
            message="{{ $acct->bank_name }} {{ $acct->account_number }} stops being offered at checkout and its QR code is deleted. Orders already paid to it keep their record."
            confirm="Delete" :action="route('admin.bank-accounts.destroy', $acct)" method="DELETE" />
    @endforeach

    <x-admin.card title="Add bank account">
        <form method="POST" action="{{ route('admin.bank-accounts.store') }}" enctype="multipart/form-data" data-v-form class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-admin.field name="bank_name" label="Bank name" required>
                    <x-admin.input name="bank_name" required placeholder="e.g. BDO, BPI, GCash" />
                </x-admin.field>
                <x-admin.field name="account_name" label="Account name" required>
                    <x-admin.input name="account_name" required placeholder="Account holder name" />
                </x-admin.field>
                <x-admin.field name="account_number" label="Account number" required>
                    <x-admin.input name="account_number" required placeholder="Account or mobile number" />
                </x-admin.field>
                <x-admin.field name="sort_order" label="Sort order">
                    <x-admin.input name="sort_order" type="number" min="0" value="0" />
                </x-admin.field>
                <x-admin.field name="qr_image" label="QR code image" class="sm:col-span-2" help="Max 2MB. Shown to the customer after order placement.">
                    <input type="file" name="qr_image" id="qr_image" accept="image/*"
                        class="block w-full text-[13px] text-v-secondary file:mr-3 file:h-8 file:rounded-v-md file:border-0 file:bg-v-sunken file:px-3 file:text-[13px] file:font-medium file:text-v-text hover:file:bg-v-hover">
                </x-admin.field>
                <x-admin.field name="instructions" label="Instructions (optional)" class="sm:col-span-2">
                    <x-admin.textarea name="instructions" rows="2" placeholder="Any additional notes for the customer&hellip;" />
                </x-admin.field>
                <div class="sm:col-span-2">
                    <input type="hidden" name="is_active" value="0">
                    <x-admin.checkbox name="is_active" label="Active" :checked="true" />
                </div>
            </div>
            <x-admin.form-actions>
                <x-admin.button variant="primary" type="submit">Add account</x-admin.button>
            </x-admin.form-actions>
        </form>
    </x-admin.card>
</div>
