<x-admin.shell title="Edit Bank Account: {{ $bankAccount->bank_name }}">
    <x-admin.page title="Edit Bank Account: {{ $bankAccount->bank_name }}" :breadcrumb="[
        ['label' => 'Settings', 'url' => route('admin.settings.index')],
        ['label' => 'Payments', 'url' => route('admin.payment-settings.index')],
        ['label' => 'Bank Transfer', 'url' => route('admin.payment-settings.show', 'bank_transfer')],
    ]">
        <x-slot:actions>
            <x-admin.button variant="ghost" :href="route('admin.payment-settings.show', 'bank_transfer')">Back to Payment</x-admin.button>
        </x-slot:actions>

        <div class="max-w-2xl">
            <x-admin.card>
                <form method="POST" action="{{ route('admin.bank-accounts.update', $bankAccount) }}" enctype="multipart/form-data" data-v-form class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-admin.field name="bank_name" label="Bank Name" required>
                            <x-admin.input type="text" name="bank_name" :value="$bankAccount->bank_name" required placeholder="e.g. BDO, BPI, GCash" />
                        </x-admin.field>
                        <x-admin.field name="account_name" label="Account Name" required>
                            <x-admin.input type="text" name="account_name" :value="$bankAccount->account_name" required placeholder="Account holder name" />
                        </x-admin.field>
                        <x-admin.field name="account_number" label="Account Number" required>
                            <x-admin.input type="text" name="account_number" :value="$bankAccount->account_number" required placeholder="Account or mobile number" />
                        </x-admin.field>
                        <x-admin.field name="sort_order" label="Sort Order">
                            <x-admin.input type="number" name="sort_order" :value="$bankAccount->sort_order" min="0" />
                        </x-admin.field>

                        {{-- QR Code --}}
                        <x-admin.field name="qr_image" label="QR Code Image" class="sm:col-span-2" help="Max 2MB. Shown to the customer after order placement.">
                            @if ($bankAccount->qr_image)
                                <div class="flex items-start gap-4 mb-3">
                                    <img src="{{ storage_url($bankAccount->qr_image) }}" alt="QR Code" class="w-32 h-32 rounded-v-md border border-v-border object-contain">
                                    <x-admin.checkbox name="remove_qr" label="Remove QR image" />
                                </div>
                            @endif
                            <input type="file" name="qr_image" id="qr_image" accept="image/*"
                                class="block w-full text-[13px] text-v-secondary file:mr-3 file:h-8 file:rounded-v-md file:border-0 file:bg-v-sunken file:px-3 file:text-[13px] file:font-medium file:text-v-text hover:file:bg-v-hover">
                        </x-admin.field>

                        {{-- Instructions --}}
                        <x-admin.field name="instructions" label="Instructions (optional)" class="sm:col-span-2">
                            <x-admin.textarea name="instructions" rows="3" :value="$bankAccount->instructions" placeholder="Any additional notes for the customer..." />
                        </x-admin.field>

                        <div class="sm:col-span-2">
                            <x-admin.toggle name="is_active" label="Active" :checked="$bankAccount->is_active" />
                        </div>
                    </div>

                    <x-admin.form-actions>
                        <x-admin.button variant="secondary" :href="route('admin.payment-settings.show', 'bank_transfer')">Cancel</x-admin.button>
                        <x-admin.button type="submit">Update Bank Account</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
