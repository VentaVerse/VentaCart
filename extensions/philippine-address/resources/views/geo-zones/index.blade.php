<x-admin-layout>
    <x-slot name="header">PH Address — Geo Zones</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.ph-address.settings') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">
            PH Settings
        </a>
        <a href="{{ route('admin.ph-address.geo-zones.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
            + New Geo Zone
        </a>
    </x-slot>

    <p class="text-sm text-gray-500 mb-4">Geo zones created here are PH-specific and use Philippine provinces. They work with shipping methods and payment restrictions just like core geo zones.</p>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        @if ($geoZones->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm font-medium text-gray-500">No PH geo zones yet</p>
                <p class="text-xs mt-1">Create geo zones using Philippine provinces for shipping and payment restrictions</p>
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($geoZones as $gz)
                    <div class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 group">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $gz->name }}</p>
                            @if ($gz->description)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $gz->description }}</p>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 shrink-0">
                            {{ $gz->entries_count }} {{ Str::plural('province', $gz->entries_count) }}
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $gz->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                            {{ $gz->is_active ? 'Active' : 'Draft' }}
                        </span>
                        <div class="flex items-center gap-2 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="{{ route('admin.ph-address.geo-zones.edit', $gz) }}" class="text-xs text-indigo-600 hover:text-indigo-900">Edit</a>
                            <form method="POST" action="{{ route('admin.ph-address.geo-zones.destroy', $gz) }}" class="inline" onsubmit="return confirm('Delete this geo zone?')">
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
