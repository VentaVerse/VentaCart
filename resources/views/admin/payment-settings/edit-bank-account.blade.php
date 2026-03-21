<x-admin-layout>
    <x-slot name="header">Edit Bank Account: {{ $bankAccount->bank_name }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.payment-settings.index', ['tab' => 'bank-transfer']) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition-colors">
            Back to Payment
        </a>
    </x-slot>

    <form method="POST" action="{{ route('admin.bank-accounts.update', $bankAccount) }}" enctype="multipart/form-data" class="max-w-2xl">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $bankAccount->bank_name) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm" placeholder="e.g. BDO, BPI, GCash">
                    @error('bank_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                    <input type="text" name="account_name" value="{{ old('account_name', $bankAccount->account_name) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm" placeholder="Account holder name">
                    @error('account_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Number</label>
                    <input type="text" name="account_number" value="{{ old('account_number', $bankAccount->account_number) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm" placeholder="Account or mobile number">
                    @error('account_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $bankAccount->sort_order) }}" min="0"
                        class="w-full rounded-lg border-gray-300 text-sm">
                </div>

                {{-- QR Code --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">QR Code Image</label>
                    @if ($bankAccount->qr_image)
                        <div class="flex items-start gap-4 mb-3">
                            <img src="{{ asset('storage/' . $bankAccount->qr_image) }}" alt="QR Code" class="w-32 h-32 rounded-lg border border-gray-200 object-contain">
                            <div>
                                <label class="flex items-center gap-2 text-sm text-red-600 cursor-pointer">
                                    <input type="checkbox" name="remove_qr" value="1" class="rounded border-gray-300 text-red-600">
                                    Remove QR image
                                </label>
                                <p class="text-xs text-gray-400 mt-1">Or upload a new one below to replace it.</p>
                            </div>
                        </div>
                    @endif
                    <input type="file" name="qr_image" accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-gray-400 mt-1">Max 2MB. Shown to customer after order placement.</p>
                    @error('qr_image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Instructions --}}
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instructions (optional)</label>
                    <textarea name="instructions" rows="3" class="w-full rounded-lg border-gray-300 text-sm"
                        placeholder="Any additional notes for the customer...">{{ old('instructions', $bankAccount->instructions) }}</textarea>
                </div>

                {{-- Active --}}
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ $bankAccount->is_active ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600">
                    <label class="text-sm text-gray-700">Active</label>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ route('admin.payment-settings.index', ['tab' => 'bank-transfer']) }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">Cancel</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Update Bank Account</button>
        </div>
    </form>
</x-admin-layout>
