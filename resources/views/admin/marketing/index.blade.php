<x-admin-layout>
    <x-slot name="header">Marketing</x-slot>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Stock Alerts --}}
        <a href="{{ route('admin.stock-notifications.index') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-indigo-300 hover:shadow-md transition-all group">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-amber-50 rounded-lg flex items-center justify-center group-hover:bg-amber-100 transition-colors">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Stock Alerts</h3>
                    <p class="text-sm text-gray-500">Back-in-stock notifications & waitlist management</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ $waitingCount }} waiting</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">{{ $notifiedCount }} notified</span>
            </div>
        </a>

        {{-- SEO --}}
        <a href="{{ route('admin.seo.index') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-indigo-300 hover:shadow-md transition-all group">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">SEO</h3>
                    <p class="text-sm text-gray-500">AI-powered optimization, sitemap & structured data</p>
                </div>
            </div>
            <div class="flex gap-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $healthScore }}% health</span>
                @if ($pendingSuggestions > 0)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ $pendingSuggestions }} pending</span>
                @endif
            </div>
        </a>
    </div>
</x-admin-layout>
