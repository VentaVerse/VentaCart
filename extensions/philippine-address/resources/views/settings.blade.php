<x-admin-layout>
    <x-slot name="header">Philippine Address (PSGC)</x-slot>

    <div class="space-y-6">
        {{-- Enable/Disable --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Extension Status</h3>
                    <p class="text-xs text-gray-500 mt-1">When enabled, Philippines appears as a country in checkout and account address forms with Province → City → District → Barangay dropdowns powered by PSGC data.</p>
                </div>
                <form method="POST" action="{{ route('admin.ph-address.toggle') }}">
                    @csrf
                    <input type="hidden" name="enabled" value="{{ $enabled ? '0' : '1' }}">
                    <button type="submit"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 {{ $enabled ? 'bg-indigo-600' : 'bg-gray-200' }}">
                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 {{ $enabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </form>
            </div>
            @if ($enabled)
                <div class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Enabled
                </div>
            @else
                <div class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    Disabled — Philippines will not appear in checkout
                </div>
            @endif
        </div>

        {{-- Geo Zones --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">PH Geo Zones</h3>
                    <p class="text-xs text-gray-500 mt-1">Create geo zones using Philippine provinces for shipping methods and payment restrictions.</p>
                </div>
                <a href="{{ route('admin.ph-address.geo-zones.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    Manage Geo Zones &rarr;
                </a>
            </div>
        </div>

        {{-- PSGC Data Stats --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">PSGC Data</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 rounded-lg bg-gray-50">
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['regions']) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Regions</div>
                </div>
                <div class="text-center p-4 rounded-lg bg-gray-50">
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['provinces']) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Provinces</div>
                </div>
                <div class="text-center p-4 rounded-lg bg-gray-50">
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['cities']) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Cities / Municipalities</div>
                </div>
                <div class="text-center p-4 rounded-lg bg-gray-50">
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['barangays']) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Barangays</div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
