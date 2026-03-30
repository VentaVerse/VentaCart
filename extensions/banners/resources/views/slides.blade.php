<x-admin-layout>
    <x-slot name="header">{{ $group->name }}</x-slot>
    <x-slot name="headerActions">
        <a href="{{ route('admin.banners.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">
            All Groups
        </a>
        <a href="{{ route('admin.banners.slides.create', $group) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
            + New Slide
        </a>
    </x-slot>

    <div class="mb-4 flex items-center gap-3 text-sm text-gray-500">
        <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $group->slug }}</span>
        <span>{{ $group->autoplay ? 'Autoplay ' . $group->speed . 'ms' : 'Manual' }}</span>
        <a href="{{ route('admin.banners.edit', $group) }}" class="text-indigo-600 hover:text-indigo-900">Settings</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        @if ($banners->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-sm font-medium text-gray-500">No slides yet</p>
                <p class="text-xs mt-1">Click "+ New Slide" to add a slide to this group</p>
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach ($banners as $banner)
                    <div class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50 group">
                        <div class="w-40 h-20 rounded-lg overflow-hidden border border-gray-200 shrink-0"
                             style="background: {{ $banner->bg_color }}">
                            @if ($banner->bg_image)
                                <img src="{{ $banner->bg_image_url }}" alt="{{ $banner->name }}" class="w-full h-full object-cover">
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $banner->name ?: 'Untitled' }}</p>
                            <p class="text-xs text-gray-500">{{ count($banner->layers ?? []) }} layers &middot; {{ $banner->height }}px</p>
                        </div>
                        <div class="text-xs text-gray-400 shrink-0">
                            @if ($banner->starts_at || $banner->ends_at)
                                {{ $banner->starts_at?->format('M d') ?? '' }} — {{ $banner->ends_at?->format('M d') ?? '∞' }}
                            @else
                                Always
                            @endif
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $banner->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">
                            {{ $banner->is_active ? 'Active' : 'Draft' }}
                        </span>
                        <div class="flex items-center gap-2 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="{{ route('admin.banners.slides.edit', [$group, $banner]) }}" class="text-xs text-indigo-600 hover:text-indigo-900">Edit</a>
                            <form method="POST" action="{{ route('admin.banners.slides.duplicate', [$group, $banner]) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs text-gray-500 hover:text-gray-700">Copy</button>
                            </form>
                            <form method="POST" action="{{ route('admin.banners.slides.destroy', [$group, $banner]) }}" class="inline" onsubmit="return confirm('Delete this slide?')">
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
