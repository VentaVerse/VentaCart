@php $config = $section->config ?? []; @endphp

@if ($section->type === 'banner')
    @php
        $bannerGroups = class_exists(\Extensions\Banners\Models\BannerGroup::class)
            ? \Extensions\Banners\Models\BannerGroup::orderBy('name')->get()
            : collect();
    @endphp
    <div class="space-y-3">
        @if ($bannerGroups->isNotEmpty())
            <div>
                <label class="block text-xs text-gray-600 mb-1">Banner Group</label>
                <select name="config[banner_group]" class="rounded-md border-gray-300 shadow-sm sm:text-sm">
                    <option value="">— Select a banner group —</option>
                    @foreach ($bannerGroups as $group)
                        <option value="{{ $group->slug }}" {{ ($config['banner_group'] ?? '') === $group->slug ? 'selected' : '' }}>
                            {{ $group->name }} ({{ $group->slides()->count() }} slides)
                        </option>
                    @endforeach
                </select>
            </div>
            <p class="text-xs text-gray-500">Slides, autoplay, and dimensions are configured in the Banner Group itself.</p>
        @else
            <p class="text-sm text-gray-500">Install the Banners extension to manage banner slides.</p>
        @endif
    </div>

@elseif ($section->type === 'products')
    <div class="space-y-3">
        <div>
            <label class="block text-xs text-gray-600 mb-1">Product Filter</label>
            <select name="config[filter]" class="rounded-md border-gray-300 shadow-sm sm:text-sm">
                <option value="new" {{ ($config['filter'] ?? '') === 'new' ? 'selected' : '' }}>New Arrivals</option>
                <option value="featured" {{ ($config['filter'] ?? '') === 'featured' ? 'selected' : '' }}>Featured</option>
                <option value="specials" {{ ($config['filter'] ?? '') === 'specials' ? 'selected' : '' }}>On Sale / Specials</option>
                <option value="category" {{ ($config['filter'] ?? '') === 'category' ? 'selected' : '' }}>By Category</option>
                <option value="brand" {{ ($config['filter'] ?? '') === 'brand' ? 'selected' : '' }}>By Brand</option>
                <option value="best_sellers" {{ ($config['filter'] ?? '') === 'best_sellers' ? 'selected' : '' }}>Best Sellers</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1">Limit</label>
            <input type="number" name="config[limit]" value="{{ $config['limit'] ?? 12 }}" min="2" max="48" class="block w-24 rounded-md border-gray-300 shadow-sm sm:text-sm">
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Mobile Cols</label>
                <input type="number" name="config[columns_mobile]" value="{{ $config['columns_mobile'] ?? 2 }}" min="1" max="3" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Tablet Cols</label>
                <input type="number" name="config[columns_tablet]" value="{{ $config['columns_tablet'] ?? 3 }}" min="2" max="4" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Desktop Cols</label>
                <input type="number" name="config[columns_desktop]" value="{{ $config['columns_desktop'] ?? 6 }}" min="3" max="8" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
        </div>
    </div>

@elseif ($section->type === 'brands')
    <div class="space-y-3">
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Per Page</label>
                <input type="number" name="config[per_page]" value="{{ $config['per_page'] ?? 8 }}" min="4" max="20" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
            <div class="flex items-end gap-2">
                <input type="hidden" name="config[autoplay]" value="0">
                <input type="checkbox" name="config[autoplay]" value="1" {{ ($config['autoplay'] ?? false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                <label class="text-sm text-gray-700">Autoplay</label>
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Speed (ms)</label>
                <input type="number" name="config[speed]" value="{{ $config['speed'] ?? 3000 }}" min="1000" step="500" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
        </div>
    </div>

@elseif ($section->type === 'category_tabs')
    <div class="space-y-3">
        <div>
            <label class="block text-xs text-gray-600 mb-1">Limit per tab</label>
            <input type="number" name="config[limit]" value="{{ $config['limit'] ?? 6 }}" min="2" max="24" class="block w-24 rounded-md border-gray-300 shadow-sm sm:text-sm">
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Mobile Cols</label>
                <input type="number" name="config[columns_mobile]" value="{{ $config['columns_mobile'] ?? 2 }}" min="1" max="3" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Tablet Cols</label>
                <input type="number" name="config[columns_tablet]" value="{{ $config['columns_tablet'] ?? 3 }}" min="2" max="4" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Desktop Cols</label>
                <input type="number" name="config[columns_desktop]" value="{{ $config['columns_desktop'] ?? 6 }}" min="3" max="8" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
        </div>
        <p class="text-xs text-gray-500">Categories for tabs will be selectable after the category selector is built.</p>
    </div>

@elseif ($section->type === 'text_block')
    <div class="space-y-3">
        <div>
            <label class="block text-xs text-gray-600 mb-1">Content</label>
            <textarea name="config[content]" rows="4" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">{{ $config['content'] ?? '' }}</textarea>
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1">Alignment</label>
            <select name="config[alignment]" class="rounded-md border-gray-300 shadow-sm sm:text-sm">
                <option value="left" {{ ($config['alignment'] ?? '') === 'left' ? 'selected' : '' }}>Left</option>
                <option value="center" {{ ($config['alignment'] ?? 'center') === 'center' ? 'selected' : '' }}>Center</option>
                <option value="right" {{ ($config['alignment'] ?? '') === 'right' ? 'selected' : '' }}>Right</option>
            </select>
        </div>
    </div>

@elseif ($section->type === 'blog_posts')
    <div class="space-y-3">
        <div>
            <label class="block text-xs text-gray-600 mb-1">Limit</label>
            <input type="number" name="config[limit]" value="{{ $config['limit'] ?? 4 }}" min="2" max="12" class="block w-24 rounded-md border-gray-300 shadow-sm sm:text-sm">
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Mobile Cols</label>
                <input type="number" name="config[columns_mobile]" value="{{ $config['columns_mobile'] ?? 2 }}" min="1" max="3" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Tablet Cols</label>
                <input type="number" name="config[columns_tablet]" value="{{ $config['columns_tablet'] ?? 3 }}" min="2" max="4" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Desktop Cols</label>
                <input type="number" name="config[columns_desktop]" value="{{ $config['columns_desktop'] ?? 4 }}" min="3" max="8" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
            </div>
        </div>
    </div>

@elseif ($section->type === 'recently_viewed')
    <div>
        <p class="text-xs text-gray-500">Shows products the visitor has recently browsed. Data comes from the browser — no configuration needed. The section only appears when the visitor has viewed at least one product.</p>
    </div>
@endif
