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
                <div class="flex items-center gap-2 mb-4">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" id="enabled" value="1" {{ $enabled ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="enabled" class="text-sm font-medium text-gray-700">Enable abandoned cart notifications</label>
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Save</button>
            </div>
        </form>

        {{-- Cron Job --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-medium text-gray-900 mb-1">Cron Job</h3>
            <p class="text-sm text-gray-500 mb-4">Add this command to your server's crontab to send abandoned cart notifications. Adjust the schedule to your preference.</p>

            <div class="flex items-center gap-2">
                <code class="flex-1 block px-3 py-2 bg-gray-50 rounded-lg border border-gray-200 text-sm font-mono text-gray-700" id="cron-cmd">/usr/bin/php {{ base_path() }}/artisan cart:notify >> /dev/null 2>&1</code>
                <button type="button" onclick="copyCron('cron-cmd')" class="px-3 py-2 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">Copy</button>
            </div>

            <p class="text-xs text-gray-400 mt-4">Add a cron schedule of your choice (e.g. <code class="bg-gray-100 px-1 rounded">0 */8 * * *</code> for every 8 hours). The command only runs when the module is enabled and at least one active rule exists.</p>
        </div>
    </div>

    <script>
        function copyCron(id) {
            var el = document.getElementById(id);
            navigator.clipboard.writeText(el.textContent.trim()).then(function() {
                var btn = el.nextElementSibling;
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = 'Copy'; }, 1500);
            });
        }
    </script>
</x-admin-layout>
