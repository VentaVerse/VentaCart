<x-admin-layout>
    <x-slot name="header">Payment</x-slot>

    @php
        $extensions = [
            ['id' => 'bank-transfer', 'label' => 'Bank Transfer', 'enabled' => ($settings['payment']['bank_transfer_enabled'] ?? '0') === '1'],
            ['id' => 'hitpay', 'label' => 'HitPay', 'enabled' => ($settings['payment']['hitpay_enabled'] ?? '0') === '1'],
            ['id' => 'cod', 'label' => 'COD', 'enabled' => ($settings['payment']['cod_enabled'] ?? '0') === '1'],
        ];
    @endphp

    <div x-data="{
        active: '{{ request('tab', session('tab', 'bank-transfer')) }}',
        hitpayMode: '{{ $settings['payment']['hitpay_mode'] ?? 'sandbox' }}',
        editField: {},
        toggleEdit(field) { this.editField[field] = !this.editField[field]; }
    }" class="flex gap-6">
        {{-- Left sidebar --}}
        <nav class="w-52 shrink-0">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Extensions</h3>
                </div>
                @foreach ($extensions as $ext)
                    <button type="button" @click="active = '{{ $ext['id'] }}'"
                        :class="active === '{{ $ext['id'] }}' ? 'bg-indigo-50 text-indigo-700 border-l-2 border-indigo-500' : 'text-gray-600 hover:bg-gray-50 border-l-2 border-transparent'"
                        class="flex items-center justify-between w-full px-4 py-2.5 text-sm font-medium transition-colors text-left">
                        <span>{{ $ext['label'] }}</span>
                        <span class="w-2 h-2 rounded-full shrink-0 {{ $ext['enabled'] ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                    </button>
                @endforeach
                <div class="border-t border-gray-100">
                    <a href="{{ route('admin.payment-restrictions.index') }}"
                        class="flex items-center w-full px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 border-l-2 border-transparent transition-colors text-left">
                        Restrictions
                    </a>
                </div>
            </div>
        </nav>

        {{-- Right content --}}
        <div class="flex-1 min-w-0">

            {{-- ==================== BANK TRANSFER ==================== --}}
            <div x-show="active === 'bank-transfer'" class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Bank Transfer</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Multi-account bank transfer with QR codes</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.update') }}" class="flex items-center gap-3">
                        @csrf
                        <input type="hidden" name="group" value="payment">
                        <input type="hidden" name="_redirect" value="/admin/payment-settings">
                        <input type="hidden" name="_tab" value="bank-transfer">
                        <input type="hidden" name="settings[bank_transfer_enabled]" value="0">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="settings[bank_transfer_enabled]" value="1" {{ ($settings['payment']['bank_transfer_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600" onchange="this.form.submit()">
                            Enabled
                        </label>
                    </form>
                </div>
                <div class="px-5 py-3 border-b border-gray-100">
                    <form method="POST" action="{{ route('admin.settings.update') }}" class="flex items-center gap-3">
                        @csrf
                        <input type="hidden" name="group" value="payment">
                        <input type="hidden" name="_redirect" value="/admin/payment-settings">
                        <input type="hidden" name="_tab" value="bank-transfer">
                        <label class="text-sm text-gray-700">Order Status on Checkout</label>
                        <select name="settings[bank_transfer_success_status_id]" class="rounded-lg border-gray-300 text-sm" onchange="this.form.submit()">
                            <option value="">Default (Pending)</option>
                            @foreach ($orderStatuses as $os)
                                <option value="{{ $os->id }}" {{ ($settings['payment']['bank_transfer_success_status_id'] ?? '') == $os->id ? 'selected' : '' }}>{{ $os->name }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="p-5">
                    {{-- Bank Accounts Table --}}
                    <div class="overflow-hidden rounded-lg border border-gray-200 mb-4">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">QR</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Number</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase w-16">Status</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 uppercase w-20"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($bankAccounts as $acct)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            @if ($acct->qr_image)
                                                <img src="{{ storage_url($acct->qr_image) }}" alt="QR" class="w-8 h-8 rounded object-contain border border-gray-200">
                                            @else
                                                <span class="text-xs text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 font-medium text-gray-900">{{ $acct->bank_name }}</td>
                                        <td class="px-4 py-2 text-gray-700">{{ $acct->account_name }}</td>
                                        <td class="px-4 py-2 text-gray-700 font-mono text-xs">{{ $acct->account_number }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="w-2 h-2 rounded-full inline-block {{ $acct->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <a href="{{ route('admin.bank-accounts.edit', $acct) }}" class="text-indigo-600 hover:text-indigo-900 text-xs mr-2">Edit</a>
                                            <form method="POST" action="{{ route('admin.bank-accounts.destroy', $acct) }}" onsubmit="return confirm('Remove?');" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">No bank accounts configured</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Add Bank Account --}}
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Add Bank Account</h4>
                        <form method="POST" action="{{ route('admin.bank-accounts.store') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-2xl">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Bank Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="bank_name" required class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="e.g. BDO, BPI, GCash">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Account Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="account_name" required class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="Account holder name">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Account Number <span class="text-red-500">*</span></label>
                                    <input type="text" name="account_number" required class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="Account or mobile number">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">QR Code Image</label>
                                    <input type="file" name="qr_image" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600">Instructions (optional)</label>
                                    <textarea name="instructions" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="Notes for the customer..."></textarea>
                                </div>
                                <div class="flex items-center gap-4">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600">
                                        Active
                                    </label>
                                    <div>
                                        <label class="text-xs text-gray-500">Sort</label>
                                        <input type="number" name="sort_order" value="0" min="0" class="w-16 rounded-md border-gray-300 text-sm ml-1">
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Add Account</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ==================== HITPAY ==================== --}}
            <div x-show="active === 'hitpay'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">HitPay</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Online payment gateway (credit card, e-wallets)</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.update') }}" class="flex items-center gap-3">
                        @csrf
                        <input type="hidden" name="group" value="payment">
                        <input type="hidden" name="_redirect" value="/admin/payment-settings">
                        <input type="hidden" name="_tab" value="hitpay">
                        <input type="hidden" name="settings[hitpay_enabled]" value="0">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="settings[hitpay_enabled]" value="1" {{ ($settings['payment']['hitpay_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600" onchange="this.form.submit()">
                            Enabled
                        </label>
                    </form>
                </div>
                <div class="p-5">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="group" value="payment">
                        <input type="hidden" name="_redirect" value="/admin/payment-settings">
                        <input type="hidden" name="_tab" value="hitpay">
                        <div class="space-y-4 max-w-lg">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mode</label>
                                <select name="settings[hitpay_mode]" x-model="hitpayMode" class="block w-48 rounded-md border-gray-300 text-sm">
                                    <option value="sandbox">Sandbox</option>
                                    <option value="production">Production</option>
                                </select>
                            </div>

                            {{-- Sandbox --}}
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
                                            <input type="password" name="settings[hitpay_sandbox_api_key]" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="Enter sandbox API key" autocomplete="off">
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
                                            <input type="password" name="settings[hitpay_sandbox_salt]" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="Enter sandbox salt" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- Production --}}
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
                                            <input type="password" name="settings[hitpay_production_api_key]" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="Enter production API key" autocomplete="off">
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
                                            <input type="password" name="settings[hitpay_production_salt]" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="Enter production salt" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Order Status on Success</label>
                                <select name="settings[hitpay_success_status_id]" class="mt-1 block w-48 rounded-md border-gray-300 text-sm">
                                    @foreach ($orderStatuses as $status)
                                        <option value="{{ $status->id }}" {{ ($settings['payment']['hitpay_success_status_id'] ?? '') == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ==================== COD ==================== --}}
            <div x-show="active === 'cod'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between p-5 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Cash on Delivery</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Customer pays upon delivery</p>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.update') }}" class="flex items-center gap-3">
                        @csrf
                        <input type="hidden" name="group" value="payment">
                        <input type="hidden" name="_redirect" value="/admin/payment-settings">
                        <input type="hidden" name="_tab" value="cod">
                        <input type="hidden" name="settings[cod_enabled]" value="0">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="settings[cod_enabled]" value="1" {{ ($settings['payment']['cod_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600" onchange="this.form.submit()">
                            Enabled
                        </label>
                    </form>
                </div>
                <div class="px-5 py-3 border-b border-gray-100">
                    <form method="POST" action="{{ route('admin.settings.update') }}" class="flex items-center gap-3">
                        @csrf
                        <input type="hidden" name="group" value="payment">
                        <input type="hidden" name="_redirect" value="/admin/payment-settings">
                        <input type="hidden" name="_tab" value="cod">
                        <label class="text-sm text-gray-700">Order Status on Checkout</label>
                        <select name="settings[cod_success_status_id]" class="rounded-lg border-gray-300 text-sm" onchange="this.form.submit()">
                            <option value="">Default (Pending)</option>
                            @foreach ($orderStatuses as $os)
                                <option value="{{ $os->id }}" {{ ($settings['payment']['cod_success_status_id'] ?? '') == $os->id ? 'selected' : '' }}>{{ $os->name }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="p-5">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <input type="hidden" name="group" value="payment">
                        <input type="hidden" name="_redirect" value="/admin/payment-settings">
                        <input type="hidden" name="_tab" value="cod">
                        <div class="space-y-4 max-w-lg">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Sort Order</label>
                                <input type="number" name="settings[cod_sort_order]" value="{{ $settings['payment']['cod_sort_order'] ?? '0' }}" min="0" class="mt-1 block w-24 rounded-md border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-admin-layout>
