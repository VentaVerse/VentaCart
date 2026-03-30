<x-admin-layout>
    <x-slot name="header">Banners</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.banners.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
            + New Group
        </a>
    </x-slot>

    <p class="text-sm text-gray-500 mb-4">Each group is a carousel placement. Add slides inside a group.</p>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        @if ($groups->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-sm font-medium text-gray-500">No banner groups yet</p>
                <p class="text-xs mt-1">Click "+ New Group" to create your first banner carousel</p>
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($groups as $group)
                    <div class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 group">
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('admin.banners.slides', $group) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $group->name }}</a>
                            <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $group->slug }}</p>
                        </div>
                        <div class="text-xs text-gray-500 shrink-0">
                            {{ $group->slides_count }} {{ Str::plural('slide', $group->slides_count) }}
                        </div>
                        <div class="text-xs text-gray-400 shrink-0">
                            {{ $group->autoplay ? $group->speed . 'ms' : 'Manual' }}
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $group->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                            {{ $group->is_active ? 'Active' : 'Draft' }}
                        </span>
                        <div class="flex items-center gap-2 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="{{ route('admin.banners.slides', $group) }}" class="text-xs text-indigo-600 hover:text-indigo-900">Slides</a>
                            <a href="{{ route('admin.banners.edit', $group) }}" class="text-xs text-gray-500 hover:text-gray-700">Settings</a>
                            <form method="POST" action="{{ route('admin.banners.destroy', $group) }}" class="inline" onsubmit="return confirm('Delete this group and all its slides?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-admin-layout>
