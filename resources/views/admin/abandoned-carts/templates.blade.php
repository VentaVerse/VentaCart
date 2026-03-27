<x-admin-layout>
    <x-slot name="header">Abandoned Carts</x-slot>

    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('admin.abandoned-carts.settings') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">General</a>
            <a href="{{ route('admin.abandoned-carts.index') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Carts</a>
            <a href="{{ route('admin.abandoned-carts.rules') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Rules</a>
            <a href="{{ route('admin.abandoned-carts.templates') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-indigo-500 text-indigo-600">Templates</a>
            <a href="{{ route('admin.abandoned-carts.notifications') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Notifications</a>
            <a href="{{ route('admin.abandoned-carts.recovered') }}" class="py-3 px-1 border-b-2 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">Recovered</a>
        </nav>
    </div>

    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-500">Email templates used by abandoned cart notification rules. These are independent from the core email templates.</p>
        <a href="{{ route('admin.abandoned-carts.templates.create') }}" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">Create Template</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($templates as $template)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $template->name }}</div>
                            <div class="text-xs text-gray-400 font-mono">{{ $template->slug }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $template->subject }}</td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.abandoned-carts.templates.toggle', $template) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer {{ $template->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right text-sm space-x-3">
                            <a href="{{ route('admin.abandoned-carts.templates.edit', $template) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            <form method="POST" action="{{ route('admin.abandoned-carts.templates.test', $template) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-gray-600 hover:text-gray-900">Send Test</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No templates found. Run: <code class="bg-gray-100 px-1 rounded">php artisan db:seed --class=AbandonedCartTemplateSeeder</code></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
