{{-- PH Reference Tab for Localization page --}}
{{-- This view is injected by the PhilippineAddress extension --}}
<div x-show="activeTab === 'ph-reference'" x-cloak class="p-6">
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-gray-500">Philippine Standard Geographic Code (PSGC) data. Read-only reference data used by address forms.</p>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">Extension</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-4 text-sm text-gray-500">
        <span class="bg-gray-50 rounded px-3 py-2 text-center">
            <span class="block text-lg font-bold text-gray-800">{{ $phStats['regions'] }}</span> Regions
        </span>
        <span class="bg-gray-50 rounded px-3 py-2 text-center">
            <span class="block text-lg font-bold text-gray-800">{{ number_format($phStats['provinces']) }}</span> Provinces
        </span>
        <span class="bg-gray-50 rounded px-3 py-2 text-center">
            <span class="block text-lg font-bold text-gray-800">{{ number_format($phStats['cities']) }}</span> Cities
        </span>
        <span class="bg-gray-50 rounded px-3 py-2 text-center">
            <span class="block text-lg font-bold text-gray-800">{{ number_format($phStats['barangays']) }}</span> Barangays
        </span>
    </div>

    <div class="space-y-2">
        @foreach ($phRegions as $region)
            <div x-data="{ open: false }" class="border border-gray-200 rounded-lg overflow-hidden">
                <button type="button" @click="open = !open"
                    class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 transition-colors text-left">
                    <div>
                        <span class="text-sm font-medium text-gray-900">{{ $region->name }}</span>
                        <span class="text-xs text-gray-500 ml-2">({{ $region->provinces->count() }} provinces)</span>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-transition class="px-4 py-3 border-t border-gray-200">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        @foreach ($region->provinces as $province)
                            <div class="text-sm text-gray-700 bg-gray-50 rounded px-3 py-1.5">
                                {{ $province->name }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
