<x-admin-layout>
    <x-slot name="header">Payment</x-slot>

    <div x-data="{
        activeTab: '{{ request('tab', session('tab', 'bank-transfer')) }}',
        hitpayMode: '{{ $settings['payment']['hitpay_mode'] ?? 'sandbox' }}',
        editField: {},
        toggleEdit(field) { this.editField[field] = !this.editField[field]; }
    }">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                @foreach ([['id' => 'bank-transfer', 'label' => 'Bank Transfer'], ['id' => 'hitpay', 'label' => 'HitPay'], ['id' => 'cod', 'label' => 'COD']] as $t)
                    <button type="button" @click="activeTab = '{{ $t['id'] }}'"
                        :class="activeTab === '{{ $t['id'] }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors">{{ $t['label'] }}</button>
                @endforeach
            </nav>

            {{-- Bank Transfer tab --}}
            <div x-show="activeTab === 'bank-transfer'" class="p-6">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="payment">
                    <input type="hidden" name="_tab" value="bank-transfer">
                    <div class="space-y-4 max-w-lg">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[bank_transfer_enabled]" value="0">
                            <input type="checkbox" name="settings[bank_transfer_enabled]" value="1" {{ ($settings['payment']['bank_transfer_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Enable Bank Transfer</label>
                        </div>

                    </div>
                    <p class="mt-3 text-xs text-gray-400">Restrictions are configured in <a href="{{ route('admin.payment-restrictions.index') }}" class="text-indigo-600 hover:underline">Payment Restrictions</a>.</p>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Bank Transfer</button>
                    </div>
                </form>

                {{-- Bank Accounts Table --}}
                <div class="border-t border-gray-200 mt-8 pt-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Bank Accounts</h3>

                    @if ($bankAccounts->isNotEmpty())
                        <div class="overflow-x-auto mb-6">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">QR</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">Bank Name</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">Account Name</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500">Account Number</th>
                                        <th class="px-4 py-2 text-center font-medium text-gray-500">Status</th>
                                        <th class="px-4 py-2 text-center font-medium text-gray-500">Order</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($bankAccounts as $acct)
                                        <tr>
                                            <td class="px-4 py-2">
                                                @if ($acct->qr_image)
                                                    <img src="{{ asset('storage/' . $acct->qr_image) }}" alt="QR" class="w-10 h-10 rounded object-contain border border-gray-200">
                                                @else
                                                    <span class="text-xs text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 font-medium text-gray-900">{{ $acct->bank_name }}</td>
                                            <td class="px-4 py-2 text-gray-700">{{ $acct->account_name }}</td>
                                            <td class="px-4 py-2 text-gray-700 font-mono">{{ $acct->account_number }}</td>
                                            <td class="px-4 py-2 text-center">
                                                @if ($acct->is_active)
                                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Active</span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Inactive</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-center text-gray-500">{{ $acct->sort_order }}</td>
                                            <td class="px-4 py-2 text-right">
                                                <a href="{{ route('admin.bank-accounts.edit', $acct) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium mr-2">Edit</a>
                                                <form method="POST" action="{{ route('admin.bank-accounts.destroy', $acct) }}" onsubmit="return confirm('Remove this bank account?');" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 mb-6">No bank accounts configured. The fallback instructions above will be shown to customers.</p>
                    @endif

                    {{-- Add Bank Account Form --}}
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Add Bank Account</h4>
                        <form method="POST" action="{{ route('admin.bank-accounts.store') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Bank Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="bank_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="e.g. BDO, BPI, GCash">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Account Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="account_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Account holder name">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Account Number <span class="text-red-500">*</span></label>
                                    <input type="text" name="account_number" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Account or mobile number">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Sort Order</label>
                                    <input type="number" name="sort_order" value="0" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">QR Code Image</label>
                                    <input type="file" name="qr_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                    <p class="text-xs text-gray-400 mt-1">Optional. Max 2MB. Shown to customer after order.</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600">Instructions (optional)</label>
                                    <textarea name="instructions" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Any additional notes for the customer..."></textarea>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600">
                                    <label class="text-sm text-gray-700">Active</label>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Add Bank Account</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- HitPay tab --}}
            <div x-show="activeTab === 'hitpay'" x-cloak class="p-6">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="payment">
                    <input type="hidden" name="_tab" value="hitpay">
                    <div class="space-y-4 max-w-lg">
                        {{-- Enable --}}
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[hitpay_enabled]" value="0">
                            <input type="checkbox" name="settings[hitpay_enabled]" value="1" {{ ($settings['payment']['hitpay_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Enable HitPay</label>
                        </div>

                        {{-- Mode --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mode</label>
                            <select name="settings[hitpay_mode]" x-model="hitpayMode" class="block w-48 rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="sandbox">Sandbox</option>
                                <option value="production">Production</option>
                            </select>
                        </div>

                        {{-- Sandbox Credentials --}}
                        <template x-if="hitpayMode === 'sandbox'">
                            <div class="space-y-4 border-l-4 border-amber-400 pl-4">
                                <p class="text-xs font-medium text-amber-600 uppercase tracking-wide">Sandbox Credentials</p>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">API Key</label>
                                    @if (!empty($maskedKeys['hitpay_sandbox_api_key']))
                                        <div x-show="!editField['sandbox_api']" class="flex items-center gap-2 mt-1">
                                            <span class="text-sm text-gray-500 font-mono">{{ $maskedKeys['hitpay_sandbox_api_key'] }}</span>
                                            <button type="button" @click="toggleEdit('sandbox_api')" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Change</button>
                                        </div>
                                    @endif
                                    <div x-show="{{ empty($maskedKeys['hitpay_sandbox_api_key']) ? 'true' : "editField['sandbox_api']" }}">
                                        <input type="password" name="settings[hitpay_sandbox_api_key]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Enter sandbox API key" autocomplete="off">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Salt</label>
                                    @if (!empty($maskedKeys['hitpay_sandbox_salt']))
                                        <div x-show="!editField['sandbox_salt']" class="flex items-center gap-2 mt-1">
                                            <span class="text-sm text-gray-500 font-mono">{{ $maskedKeys['hitpay_sandbox_salt'] }}</span>
                                            <button type="button" @click="toggleEdit('sandbox_salt')" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Change</button>
                                        </div>
                                    @endif
                                    <div x-show="{{ empty($maskedKeys['hitpay_sandbox_salt']) ? 'true' : "editField['sandbox_salt']" }}">
                                        <input type="password" name="settings[hitpay_sandbox_salt]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Enter sandbox salt" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Production Credentials --}}
                        <template x-if="hitpayMode === 'production'">
                            <div class="space-y-4 border-l-4 border-green-500 pl-4">
                                <p class="text-xs font-medium text-green-600 uppercase tracking-wide">Production Credentials</p>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">API Key</label>
                                    @if (!empty($maskedKeys['hitpay_production_api_key']))
                                        <div x-show="!editField['prod_api']" class="flex items-center gap-2 mt-1">
                                            <span class="text-sm text-gray-500 font-mono">{{ $maskedKeys['hitpay_production_api_key'] }}</span>
                                            <button type="button" @click="toggleEdit('prod_api')" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Change</button>
                                        </div>
                                    @endif
                                    <div x-show="{{ empty($maskedKeys['hitpay_production_api_key']) ? 'true' : "editField['prod_api']" }}">
                                        <input type="password" name="settings[hitpay_production_api_key]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Enter production API key" autocomplete="off">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Salt</label>
                                    @if (!empty($maskedKeys['hitpay_production_salt']))
                                        <div x-show="!editField['prod_salt']" class="flex items-center gap-2 mt-1">
                                            <span class="text-sm text-gray-500 font-mono">{{ $maskedKeys['hitpay_production_salt'] }}</span>
                                            <button type="button" @click="toggleEdit('prod_salt')" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Change</button>
                                        </div>
                                    @endif
                                    <div x-show="{{ empty($maskedKeys['hitpay_production_salt']) ? 'true' : "editField['prod_salt']" }}">
                                        <input type="password" name="settings[hitpay_production_salt]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Enter production salt" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Success Order Status --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Order Status on Successful Payment</label>
                            <p class="text-xs text-gray-500 mb-1">Order status set when HitPay confirms payment.</p>
                            <select name="settings[hitpay_success_status_id]" class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm sm:text-sm">
                                @foreach ($orderStatuses as $status)
                                    <option value="{{ $status->id }}" {{ ($settings['payment']['hitpay_success_status_id'] ?? '') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    <p class="mt-3 text-xs text-gray-400">Restrictions are configured in <a href="{{ route('admin.payment-restrictions.index') }}" class="text-indigo-600 hover:underline">Payment Restrictions</a>.</p>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save HitPay</button>
                    </div>
                </form>
            </div>

            {{-- COD tab --}}
            <div x-show="activeTab === 'cod'" x-cloak class="p-6">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="payment">
                    <input type="hidden" name="_tab" value="cod">
                    <div class="space-y-4 max-w-lg">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[cod_enabled]" value="0">
                            <input type="checkbox" name="settings[cod_enabled]" value="1" {{ ($settings['payment']['cod_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Enable Cash on Delivery</label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sort Order</label>
                            <input type="number" name="settings[cod_sort_order]" value="{{ $settings['payment']['cod_sort_order'] ?? '0' }}" min="0" class="mt-1 block w-24 rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>

                    </div>
                    <p class="mt-3 text-xs text-gray-400">Restrictions are configured in <a href="{{ route('admin.payment-restrictions.index') }}" class="text-indigo-600 hover:underline">Payment Restrictions</a>.</p>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save COD</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
