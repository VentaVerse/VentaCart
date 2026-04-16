<x-admin-layout>
    <x-slot name="header">Abandoned Carts</x-slot>

    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('admin.abandoned-carts.settings') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-indigo-500 text-indigo-600">General</a>
            <a href="{{ route('admin.abandoned-carts.index') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Carts</a>
            <a href="{{ route('admin.abandoned-carts.rules') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Rules</a>
            <a href="{{ route('admin.abandoned-carts.templates') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Templates</a>
            <a href="{{ route('admin.abandoned-carts.notifications') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Notifications</a>
            <a href="{{ route('admin.abandoned-carts.recovered') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Recovered</a>
        </nav>
    </div>

    <div class="max-w-2xl space-y-6">
        {{-- Enable/Disable --}}
        <form method="POST" action="{{ route('admin.abandoned-carts.settings.update') }}">
            @csrf
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-medium text-gray-900">Abandoned Cart Notifications</h3>
                <p class="text-sm text-gray-500 mt-1 mb-4">When enabled, the cron command will process rules and send notification emails to customers with abandoned carts.</p>
                <div class="flex items-center gap-2 mb-3">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" id="enabled" value="1" {{ $enabled ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="enabled" class="text-sm font-medium text-gray-700">Enable abandoned cart notifications</label>
                </div>
                <div class="flex items-center gap-2 mb-4">
                    <input type="hidden" name="skip_out_of_stock" value="0">
                    <input type="checkbox" name="skip_out_of_stock" id="skip_out_of_stock" value="1" {{ $skipOutOfStock ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="skip_out_of_stock" class="text-sm text-gray-700">Skip notifications when all cart items are out of stock</label>
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Save</button>
            </div>
        </form>

        {{-- Scheduling --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-medium text-gray-900 mb-1">Scheduling</h3>
            <p class="text-sm text-gray-500 mb-3">Abandoned cart notifications run automatically via the Venta scheduler. Change how often they fire — or run them manually — in the Automations panel.</p>

            <a href="{{ route('admin.settings.index', ['tab' => 'automations']) }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Manage in Settings → Automations
            </a>

            <div class="mt-4 p-3 rounded-md bg-amber-50 border border-amber-200 text-xs text-amber-800">
                <strong>Upgrading from an older Venta install?</strong> If your server's crontab still has a line running <code class="bg-amber-100 px-1 rounded">cart:notify</code> directly, remove it — the command now fires through the universal scheduler and the old line will cause duplicate runs.
            </div>
        </div>
    </div>
</x-admin-layout>
