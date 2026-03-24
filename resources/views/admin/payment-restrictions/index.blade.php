<x-admin-layout>
    <x-slot name="breadcrumb">
        <a href="{{ route('admin.payment-settings.index') }}" class="text-gray-500 hover:text-gray-700 font-medium">Payment</a>
    </x-slot>
    <x-slot name="header">Restrictions</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.payment-restrictions.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Restriction
        </a>
    </x-slot>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <p class="text-xs text-gray-500">Configure conditions that must be met for each payment method to appear at checkout. All inclusion conditions use AND logic (all must pass). Exclusions block if any match.</p>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conditions</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($restrictions as $restriction)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ \App\Models\PaymentMethodRestriction::getPaymentMethodLabel($restriction->payment_method) }}</div>
                            <div class="text-xs text-gray-400 font-mono">{{ $restriction->payment_method }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-xs text-gray-500 max-w-lg">{{ $restriction->getConditionsSummary() }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.payment-restrictions.toggle', $restriction) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $restriction->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $restriction->is_active ? 'Active' : 'Disabled' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right text-sm">
                            <a href="{{ route('admin.payment-restrictions.edit', $restriction) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                            <form method="POST" action="{{ route('admin.payment-restrictions.destroy', $restriction) }}" class="inline" onsubmit="return confirm('Remove restriction for this payment method?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                            No payment restrictions configured. All payment methods are available everywhere.
                            <br>
                            <a href="{{ route('admin.payment-restrictions.create') }}" class="text-indigo-600 hover:underline">Add your first restriction</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
