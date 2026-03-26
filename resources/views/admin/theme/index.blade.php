<x-admin-layout>
    <x-slot name="header">Theme Settings</x-slot>

    {{-- Theme Selector --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Active Theme</h3>
                <p class="text-sm text-gray-500 mt-1">{{ $installedThemes[$activeTheme]['name'] ?? $activeTheme }} — {{ $installedThemes[$activeTheme]['description'] ?? '' }}</p>
            </div>
            <form method="POST" action="{{ route('admin.theme.switch') }}" class="flex items-center gap-3">
                @csrf
                <select name="theme" class="rounded-md border-gray-300 shadow-sm sm:text-sm">
                    @foreach ($installedThemes as $slug => $meta)
                        <option value="{{ $slug }}" {{ $slug === $activeTheme ? 'selected' : '' }}>{{ $meta['name'] }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Switch</button>
            </form>
        </div>
    </div>

    <div x-data="themeAdmin()">
        {{-- Tab Navigation --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <nav class="flex border-b border-gray-200 overflow-x-auto">
                <template x-for="t in tabs" :key="t.id">
                    <button type="button" @click="activeTab = t.id"
                        :class="activeTab === t.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap px-5 py-3 border-b-2 text-sm font-medium transition-colors"
                        x-text="t.label"></button>
                </template>
            </nav>

            {{-- ==================== COLORS ==================== --}}
            <div x-show="activeTab === 'colors'" class="p-6">
                <form method="POST" action="{{ route('admin.theme.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="colors">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-2xl">
                        @php
                            $colorFields = [
                                'primary' => 'Primary',
                                'secondary' => 'Secondary',
                                'nav_bg' => 'Nav Background',
                                'nav_text' => 'Nav Text',
                                'footer_bg' => 'Footer Background',
                                'footer_text' => 'Footer Text',
                                'announcement_bg' => 'Announcement BG',
                                'announcement_text' => 'Announcement Text',
                            ];
                        @endphp
                        @foreach ($colorFields as $key => $label)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" value="{{ $settings['colors'][$key] ?? '#000000' }}" class="h-10 w-14 rounded border border-gray-300 cursor-pointer"
                                        oninput="this.nextElementSibling.value = this.value">
                                    <input type="text" name="settings[{{ $key }}]" value="{{ $settings['colors'][$key] ?? '#000000' }}" class="w-24 text-xs font-mono border-gray-300 rounded"
                                        oninput="this.previousElementSibling.value = this.value"
                                        placeholder="#000000">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-6 flex items-center justify-between">
                        <button type="button" onclick="if(confirm('Revert all colors and fonts to theme defaults?')) document.getElementById('revert-colors-form').submit()"
                            class="text-xs text-gray-500 hover:text-red-600 font-medium">Revert to Default</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Colors</button>
                    </div>
                </form>
                <form id="revert-colors-form" method="POST" action="{{ route('admin.theme.revert-colors') }}" class="hidden">
                    @csrf
                </form>
            </div>

            {{-- ==================== FONTS ==================== --}}
            <div x-show="activeTab === 'fonts'" class="p-6">
                <form method="POST" action="{{ route('admin.theme.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="fonts">
                    <div class="space-y-4 max-w-md">
                        @php
                            $fontOptions = ['Oswald','Montserrat','Roboto','Roboto Slab','Open Sans','Lato','Poppins','Raleway','Playfair Display','Merriweather','Nunito','Inter','PT Sans','Source Sans 3','Ubuntu'];
                        @endphp
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Heading Font</label>
                            <select name="settings[heading]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @foreach ($fontOptions as $font)
                                    <option value="{{ $font }}" {{ ($settings['fonts']['heading'] ?? '') === $font ? 'selected' : '' }}>{{ $font }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Body Font</label>
                            <select name="settings[body]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @foreach ($fontOptions as $font)
                                    <option value="{{ $font }}" {{ ($settings['fonts']['body'] ?? '') === $font ? 'selected' : '' }}>{{ $font }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Fonts</button>
                    </div>
                </form>
            </div>

            {{-- ==================== HEADER ==================== --}}
            <div x-show="activeTab === 'header'" class="p-6">
                <form method="POST" action="{{ route('admin.theme.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="header">
                    <div class="space-y-6 max-w-lg">
                        <h3 class="text-sm font-semibold text-gray-700 border-b pb-2">Announcement Bar</h3>
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[announcement_enabled]" value="0">
                            <input type="checkbox" name="settings[announcement_enabled]" value="1" {{ ($settings['header']['announcement_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Enable Announcement Bar</label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Announcement Text</label>
                            <input type="text" name="settings[announcement_text]" value="{{ $settings['header']['announcement_text'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Announcement Link (optional)</label>
                            <input type="text" name="settings[announcement_link]" value="{{ $settings['header']['announcement_link'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="/page-url or https://...">
                        </div>

                        <h3 class="text-sm font-semibold text-gray-700 border-b pb-2 mt-8">Top Bar Links</h3>
                        <div x-data="topbarLinks()" class="space-y-2">
                            <template x-for="(link, idx) in links" :key="idx">
                                <div class="flex items-center gap-2">
                                    <input type="text" x-model="link.label" class="flex-1 rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Label">
                                    <input type="text" x-model="link.url" class="flex-1 rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="/url">
                                    <button type="button" @click="links.splice(idx, 1)" class="text-red-500 hover:text-red-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </template>
                            <button type="button" @click="links.push({label:'',url:''})" class="text-sm text-indigo-600 hover:text-indigo-800">+ Add Link</button>
                            <input type="hidden" name="settings[topbar_links]" :value="JSON.stringify(links)">
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Header</button>
                    </div>
                </form>
            </div>

            {{-- ==================== NAVIGATION ==================== --}}
            <div x-show="activeTab === 'navigation'" class="p-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Menu Items</h3>
                    </div>

                    {{-- Existing items --}}
                    <div class="space-y-2" x-init="initSortable($el, '{{ route('admin.theme.nav.reorder') }}')">
                        @foreach ($navItems as $item)
                            <div class="flex items-center gap-3 bg-gray-50 rounded-lg p-3"
                                 draggable="true" data-id="{{ $item->id }}">
                                <svg class="w-5 h-5 text-gray-400 cursor-grab flex-shrink-0 drag-handle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-medium">{{ $item->label }}</span>
                                    <span class="text-xs text-gray-500 ml-2">
                                        ({{ $item->type === 'category' ? 'Category: ' . ($item->category?->name ?? 'N/A') : 'Custom: ' . $item->url }})
                                    </span>
                                </div>
                                <span class="text-xs {{ $item->is_enabled ? 'text-green-600' : 'text-red-500' }}">
                                    {{ $item->is_enabled ? 'Active' : 'Disabled' }}
                                </span>
                                <form method="POST" action="{{ route('admin.theme.nav.destroy', $item) }}" class="inline"
                                    onsubmit="return confirm('Delete this menu item?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    {{-- Add new item --}}
                    <div class="border-t pt-4 mt-4" x-data="{ newType: 'category' }">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Add Menu Item</h4>
                        <form method="POST" action="{{ route('admin.theme.nav.store') }}" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-2 gap-3 max-w-lg">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Type</label>
                                    <select name="type" x-model="newType" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                        <option value="category">Category</option>
                                        <option value="custom">Custom Link</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Label</label>
                                    <input type="text" name="label" required class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Menu label">
                                </div>
                            </div>
                            <div x-show="newType === 'category'" class="max-w-lg">
                                <label class="block text-xs text-gray-600 mb-1">Category</label>
                                <select name="category_id" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="">-- Select --</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="newType === 'custom'" class="max-w-lg">
                                <label class="block text-xs text-gray-600 mb-1">URL</label>
                                <input type="text" name="url" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="/blog or https://...">
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="checkbox" name="new_tab" value="1" class="rounded border-gray-300 text-indigo-600">
                                <label class="text-sm text-gray-700">Open in new tab</label>
                            </div>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Add Item</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ==================== HOMEPAGE ==================== --}}
            <div x-show="activeTab === 'homepage'" class="p-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Homepage Sections</h3>
                    </div>

                    {{-- Existing sections --}}
                    <div class="space-y-2" x-init="initSortable($el, '{{ route('admin.theme.sections.reorder') }}')">
                        @foreach ($sections as $section)
                            <div class="bg-gray-50 rounded-lg p-4" x-data="{ expanded: false }"
                                 draggable="true" data-id="{{ $section->id }}">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-gray-400 cursor-grab flex-shrink-0 drag-handle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">{{ $section->type }}</span>
                                    <span class="text-sm font-medium flex-1">{{ $section->title }}</span>
                                    <span class="text-xs {{ $section->is_enabled ? 'text-green-600' : 'text-red-500' }}">
                                        {{ $section->is_enabled ? 'Active' : 'Disabled' }}
                                    </span>
                                    <button type="button" @click="expanded = !expanded" class="text-gray-500 hover:text-gray-700">
                                        <svg class="w-5 h-5 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <form method="POST" action="{{ route('admin.theme.sections.destroy', $section) }}" class="inline" onsubmit="return confirm('Delete this section?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </form>
                                </div>

                                <div x-show="expanded" x-collapse class="mt-4 border-t pt-4">
                                    <form method="POST" action="{{ route('admin.theme.sections.update', $section) }}" class="space-y-3">
                                        @csrf @method('PUT')
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs text-gray-600 mb-1">Title</label>
                                                <input type="text" name="title" value="{{ $section->title }}" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                            </div>
                                            <div class="flex items-end gap-3">
                                                <label class="flex items-center gap-2">
                                                    <input type="hidden" name="is_enabled" value="0">
                                                    <input type="checkbox" name="is_enabled" value="1" {{ $section->is_enabled ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                                    <span class="text-sm text-gray-700">Enabled</span>
                                                </label>
                                            </div>
                                        </div>

                                        @include('admin.theme.section-config', ['section' => $section])

                                        <div class="flex justify-end">
                                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">Update Section</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Add new section --}}
                    <div class="border-t pt-4 mt-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Add Section</h4>
                        <form method="POST" action="{{ route('admin.theme.sections.store') }}" class="flex items-end gap-3 max-w-lg">
                            @csrf
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 mb-1">Type</label>
                                <select name="type" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="banner">Banner/Slider</option>
                                    <option value="products">Products Carousel</option>
                                    <option value="brands">Brands Carousel</option>
                                    <option value="category_tabs">Category Tabs</option>
                                    <option value="text_block">Text Block</option>
                                    <option value="blog_posts">Blog Posts</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-600 mb-1">Title</label>
                                <input type="text" name="title" required class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Section title">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Add</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ==================== PRODUCT PAGE ==================== --}}
            <div x-show="activeTab === 'product_page'" class="p-6">
                <form method="POST" action="{{ route('admin.theme.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="product_page">
                    <div class="space-y-4 max-w-lg">
                        @php
                            $ppToggles = [
                                'show_ratings' => 'Show Star Ratings',
                                'show_stock' => 'Show Stock/Availability',
                                'show_brand' => 'Show Brand Logo',
                                'show_badges' => 'Show Product Badges',
                            ];
                        @endphp
                        @foreach ($ppToggles as $key => $label)
                            <div class="flex items-center gap-3">
                                <input type="hidden" name="settings[{{ $key }}]" value="0">
                                <input type="checkbox" name="settings[{{ $key }}]" value="1" {{ ($settings['product_page'][$key] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                <label class="text-sm text-gray-700">{{ $label }}</label>
                            </div>
                        @endforeach

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Thumbnail Position</label>
                            <select name="settings[thumbnail_position]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="left" {{ ($settings['product_page']['thumbnail_position'] ?? 'left') === 'left' ? 'selected' : '' }}>Left</option>
                                <option value="below" {{ ($settings['product_page']['thumbnail_position'] ?? '') === 'below' ? 'selected' : '' }}>Below</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Product Page</button>
                    </div>
                </form>
            </div>

            {{-- ==================== CATEGORY PAGE ==================== --}}
            <div x-show="activeTab === 'category_page'" class="p-6">
                <form method="POST" action="{{ route('admin.theme.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="category_page">
                    <div class="space-y-4 max-w-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Products Per Page</label>
                            <input type="number" name="settings[products_per_page]" value="{{ $settings['category_page']['products_per_page'] ?? '12' }}" min="4" max="60" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Mobile Columns</label>
                                <input type="number" name="settings[columns_mobile]" value="{{ $settings['category_page']['columns_mobile'] ?? '2' }}" min="1" max="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tablet Columns</label>
                                <input type="number" name="settings[columns_tablet]" value="{{ $settings['category_page']['columns_tablet'] ?? '3' }}" min="2" max="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Desktop Columns</label>
                                <input type="number" name="settings[columns_desktop]" value="{{ $settings['category_page']['columns_desktop'] ?? '4' }}" min="3" max="8" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Default Sort</label>
                            <select name="settings[default_sort]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="default" {{ ($settings['category_page']['default_sort'] ?? '') === 'default' ? 'selected' : '' }}>Default</option>
                                <option value="name_asc" {{ ($settings['category_page']['default_sort'] ?? '') === 'name_asc' ? 'selected' : '' }}>Name A-Z</option>
                                <option value="name_desc" {{ ($settings['category_page']['default_sort'] ?? '') === 'name_desc' ? 'selected' : '' }}>Name Z-A</option>
                                <option value="price_asc" {{ ($settings['category_page']['default_sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>Price Low-High</option>
                                <option value="price_desc" {{ ($settings['category_page']['default_sort'] ?? '') === 'price_desc' ? 'selected' : '' }}>Price High-Low</option>
                                <option value="newest" {{ ($settings['category_page']['default_sort'] ?? '') === 'newest' ? 'selected' : '' }}>Newest</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-8 border-t pt-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-4">Sidebar Filters</h3>
                        <div class="space-y-3 max-w-md">
                            @php
                                $filterToggles = [
                                    'price_enabled' => 'Price Range',
                                    'availability_enabled' => 'Availability',
                                    'brand_enabled' => 'Brand',
                                    'rating_enabled' => 'Rating',
                                    'on_sale_enabled' => 'On Sale',
                                    'tags_enabled' => 'Tags',
                                ];
                            @endphp
                            @foreach ($filterToggles as $key => $label)
                                <div class="flex items-center gap-3">
                                    <input type="hidden" name="filters[{{ $key }}]" value="0">
                                    <input type="checkbox" name="filters[{{ $key }}]" value="1" {{ ($settings['filters'][$key] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                    <label class="text-sm text-gray-700">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Category</button>
                    </div>
                </form>
            </div>

            {{-- ==================== BADGES ==================== --}}
            <div x-show="activeTab === 'badges'" class="p-6">
                <form method="POST" action="{{ route('admin.theme.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="badges">
                    <div class="space-y-4 max-w-md">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">NEW Badge — Last X products</label>
                            <input type="number" name="settings[new_count]" value="{{ $settings['badges']['new_count'] ?? '10' }}" min="0" max="100" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm sm:text-sm">
                            <p class="text-xs text-gray-500 mt-1">Products added most recently. Set 0 to disable.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">HOT Badge — Top X best sellers</label>
                            <input type="number" name="settings[hot_count]" value="{{ $settings['badges']['hot_count'] ?? '5' }}" min="0" max="100" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm sm:text-sm">
                            <p class="text-xs text-gray-500 mt-1">Products with the most orders. Set 0 to disable.</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Badges</button>
                    </div>
                </form>
            </div>

            {{-- ==================== FOOTER ==================== --}}
            <div x-show="activeTab === 'footer'" class="p-6">
                <form method="POST" action="{{ route('admin.theme.footer.bulk-update') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold text-gray-700">Footer Columns</h3>

                        <div x-init="initSortable($el, '{{ route('admin.theme.footer.reorder') }}')">
                        @foreach ($footerColumns as $colIdx => $col)
                            <div class="bg-gray-50 rounded-lg overflow-hidden mb-2" data-id="{{ $col->id }}"
                                 draggable="true"
                                 x-data="{
                                open: false,
                                links: {{ json_encode($col->config['links'] ?? []) }},
                                socials: {{ json_encode($col->type === 'social' ? ($col->config['links'] ?? []) : []) }},
                                payIcons: {{ json_encode(collect($col->config['icons'] ?? [])->map(function ($icon) { return is_string($icon) ? ['type' => 'text', 'value' => $icon] : $icon; })->values()->toArray()) }},
                            }">
                                <input type="hidden" name="columns[{{ $col->id }}][id]" value="{{ $col->id }}">

                                {{-- Header --}}
                                <div class="flex items-center gap-3 p-4 cursor-pointer" @click="open = !open">
                                    <svg class="w-5 h-5 text-gray-400 cursor-grab flex-shrink-0 drag-handle" @click.stop fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ $col->type }}</span>
                                    <input type="text" name="columns[{{ $col->id }}][title]" value="{{ $col->title }}" class="text-sm font-medium flex-1 bg-transparent border-0 p-0 focus:ring-0" @click.stop>
                                    <label class="flex items-center gap-1" @click.stop>
                                        <input type="hidden" name="columns[{{ $col->id }}][is_enabled]" value="0">
                                        <input type="checkbox" name="columns[{{ $col->id }}][is_enabled]" value="1" {{ $col->is_enabled ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 w-3.5 h-3.5">
                                    </label>
                                    <button type="button" @click.stop="if(confirm('Delete this column?')) document.getElementById('delete-col-{{ $col->id }}').submit()" class="text-red-400 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>

                                {{-- Expanded edit --}}
                                <div x-show="open" x-transition class="border-t border-gray-200 p-4 bg-white" @click.stop>
                                    @if ($col->type === 'links')
                                        <div class="space-y-2 mb-3">
                                            <template x-for="(link, idx) in links" :key="idx">
                                                <div class="flex items-center gap-2">
                                                    <input type="text" :name="'columns[{{ $col->id }}][config][links]['+idx+'][label]'" x-model="link.label" placeholder="Label" class="flex-1 rounded-md border-gray-300 text-sm">
                                                    <input type="text" :name="'columns[{{ $col->id }}][config][links]['+idx+'][url]'" x-model="link.url" placeholder="/slug" class="flex-1 rounded-md border-gray-300 text-sm font-mono">
                                                    <button type="button" @click="links.splice(idx, 1)" class="text-red-400 hover:text-red-600 p-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                        <button type="button" @click="links.push({label: '', url: ''})" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add Link</button>

                                    @elseif ($col->type === 'social')
                                        <div class="space-y-2 mb-3">
                                            <template x-for="(social, idx) in socials" :key="idx">
                                                <div class="flex items-center gap-2">
                                                    <select :name="'columns[{{ $col->id }}][config][links]['+idx+'][platform]'" x-model="social.platform" class="w-32 rounded-md border-gray-300 text-sm">
                                                        <option value="facebook">Facebook</option>
                                                        <option value="instagram">Instagram</option>
                                                        <option value="youtube">YouTube</option>
                                                        <option value="tiktok">TikTok</option>
                                                    </select>
                                                    <input type="text" :name="'columns[{{ $col->id }}][config][links]['+idx+'][url]'" x-model="social.url" placeholder="https://..." class="flex-1 rounded-md border-gray-300 text-sm font-mono">
                                                    <button type="button" @click="socials.splice(idx, 1)" class="text-red-400 hover:text-red-600 p-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                        <button type="button" @click="socials.push({platform: 'facebook', url: ''})" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add Social</button>

                                    @elseif ($col->type === 'payment_icons')
                                        <div class="space-y-2 mb-3">
                                            <template x-for="(icon, idx) in payIcons" :key="idx">
                                                <div class="flex items-center gap-2 bg-gray-50 rounded p-2">
                                                    <select :name="'columns[{{ $col->id }}][config][icons]['+idx+'][type]'" x-model="icon.type" class="w-20 rounded-md border-gray-300 text-xs">
                                                        <option value="text">Text</option>
                                                        <option value="image">Image</option>
                                                    </select>
                                                    <template x-if="icon.type === 'text'">
                                                        <input type="text" :name="'columns[{{ $col->id }}][config][icons]['+idx+'][value]'" x-model="icon.value" placeholder="e.g. Visa" class="flex-1 rounded-md border-gray-300 text-sm">
                                                    </template>
                                                    <template x-if="icon.type === 'image'">
                                                        <div class="flex-1 flex items-center gap-2">
                                                            <template x-if="icon.value">
                                                                <img :src="icon.value.startsWith('http') || icon.value.startsWith('/') ? icon.value : '/storage/' + icon.value" class="h-6 rounded" alt="">
                                                            </template>
                                                            <input type="hidden" :name="'columns[{{ $col->id }}][config][icons]['+idx+'][value]'" :value="icon.value">
                                                            <input type="file" :name="'icon_uploads[{{ $col->id }}]['+idx+']'" accept="image/*" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-indigo-50 file:text-indigo-600">
                                                        </div>
                                                    </template>
                                                    <button type="button" @click="payIcons.splice(idx, 1)" class="text-red-400 hover:text-red-600 p-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                        <button type="button" @click="payIcons.push({type: 'text', value: ''})" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add Payment Method</button>

                                    @elseif ($col->type === 'text')
                                        <textarea name="columns[{{ $col->id }}][config][content]" rows="3" class="w-full rounded-md border-gray-300 text-sm" placeholder="Free text content...">{{ $col->config['content'] ?? '' }}</textarea>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Footer</button>
                    </div>
                </form>

                @foreach ($footerColumns as $col)
                    <form id="delete-col-{{ $col->id }}" method="POST" action="{{ route('admin.theme.footer.destroy', $col) }}" class="hidden">
                        @csrf @method('DELETE')
                    </form>
                @endforeach

                <div class="border-t pt-4 mt-6">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Add Footer Column</h4>
                    <form method="POST" action="{{ route('admin.theme.footer.store') }}" class="flex items-end gap-3 max-w-lg">
                        @csrf
                        <div class="flex-1">
                            <label class="block text-xs text-gray-600 mb-1">Type</label>
                            <select name="type" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="links">Links</option>
                                <option value="payment_icons">Payment Icons</option>
                                <option value="social">Social Links</option>
                                <option value="text">Free Text</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs text-gray-600 mb-1">Title</label>
                            <input type="text" name="title" required class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Add</button>
                    </form>
                </div>
            </div>

            {{-- ==================== BLOG ==================== --}}
            <div x-show="activeTab === 'blog'" class="p-6">
                <form method="POST" action="{{ route('admin.theme.settings.update') }}">
                    @csrf
                    <input type="hidden" name="group" value="blog">
                    <div class="space-y-4 max-w-md">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Posts Per Page</label>
                            <input type="number" name="settings[posts_per_page]" value="{{ $settings['blog']['posts_per_page'] ?? '12' }}" min="4" max="48" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Default View</label>
                            <select name="settings[default_view]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="grid" {{ ($settings['blog']['default_view'] ?? 'grid') === 'grid' ? 'selected' : '' }}>Grid</option>
                                <option value="list" {{ ($settings['blog']['default_view'] ?? '') === 'list' ? 'selected' : '' }}>List</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Excerpt Length (characters)</label>
                            <input type="number" name="settings[excerpt_length]" value="{{ $settings['blog']['excerpt_length'] ?? '150' }}" min="50" max="500" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[show_view_count]" value="0">
                            <input type="checkbox" name="settings[show_view_count]" value="1" {{ ($settings['blog']['show_view_count'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Show View Count</label>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="settings[show_related_articles]" value="0">
                            <input type="checkbox" name="settings[show_related_articles]" value="1" {{ ($settings['blog']['show_related_articles'] ?? '0') === '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <label class="text-sm text-gray-700">Show Related Articles on Product Pages</label>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Blog Settings</button>
                    </div>
                </form>
            </div>

            {{-- ==================== THEME SETTINGS ==================== --}}
            <div x-show="activeTab === 'theme-settings'" class="p-6">
                @if (count($themeSettingsSchema) > 0)
                    <form method="POST" action="{{ route('admin.theme.update-theme-settings') }}">
                        @csrf
                        <div class="space-y-4 max-w-md">
                            <p class="text-sm text-gray-500 mb-4">Settings specific to the <strong>{{ $installedThemes[$activeTheme]['name'] ?? $activeTheme }}</strong> theme.</p>
                            @foreach ($themeSettingsSchema as $key => $schema)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $schema['label'] ?? $key }}</label>
                                    @if (($schema['type'] ?? '') === 'select')
                                        <select name="theme_settings[{{ $key }}]" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                            @foreach ($schema['options'] ?? [] as $option)
                                                <option value="{{ $option }}" {{ ($themeSettingValues[$key] ?? ($schema['default'] ?? '')) == $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                                            @endforeach
                                        </select>
                                    @elseif (($schema['type'] ?? '') === 'toggle')
                                        <div class="flex items-center gap-2">
                                            <input type="hidden" name="theme_settings[{{ $key }}]" value="0">
                                            <input type="checkbox" name="theme_settings[{{ $key }}]" value="1"
                                                {{ ($themeSettingValues[$key] ?? ($schema['default'] ?? false)) ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600">
                                            <span class="text-sm text-gray-600">Enabled</span>
                                        </div>
                                    @elseif (($schema['type'] ?? '') === 'color')
                                        <input type="color" name="theme_settings[{{ $key }}]" value="{{ $themeSettingValues[$key] ?? ($schema['default'] ?? '#000000') }}" class="h-10 w-14 rounded border border-gray-300 cursor-pointer">
                                    @elseif (($schema['type'] ?? '') === 'number')
                                        <input type="number" name="theme_settings[{{ $key }}]" value="{{ $themeSettingValues[$key] ?? ($schema['default'] ?? '') }}" class="block w-32 rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    @else
                                        <input type="text" name="theme_settings[{{ $key }}]" value="{{ $themeSettingValues[$key] ?? ($schema['default'] ?? '') }}" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Save Theme Settings</button>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-gray-500 italic">This theme has no configurable settings.</p>
                @endif
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        function themeAdmin() {
            return {
                activeTab: '{{ session('tab', 'colors') }}',
                tabs: [
                    { id: 'colors', label: 'Colors' },
                    { id: 'fonts', label: 'Fonts' },
                    { id: 'header', label: 'Header' },
                    { id: 'navigation', label: 'Navigation' },
                    { id: 'homepage', label: 'Homepage' },
                    { id: 'product_page', label: 'Product Page' },
                    { id: 'category_page', label: 'Category Page' },
                    { id: 'badges', label: 'Badges' },
                    { id: 'footer', label: 'Footer' },
                    { id: 'blog', label: 'Blog' },
                    { id: 'theme-settings', label: 'Theme Settings' },
                ],
            };
        }

        function topbarLinks() {
            return {
                links: @json(json_decode($settings['header']['topbar_links'] ?? '[]', true) ?: []),
            };
        }
        function initSortable(container, reorderUrl) {
            let dragEl = null;

            container.addEventListener('dragstart', function(e) {
                const item = e.target.closest('[data-id]');
                if (!item) return;
                dragEl = item;
                item.style.opacity = '0.4';
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', item.dataset.id);
            });

            container.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (!dragEl) return;
                const target = e.target.closest('[data-id]');
                if (!target || target === dragEl) return;
                const rect = target.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                if (e.clientY < midY) {
                    target.before(dragEl);
                } else {
                    target.after(dragEl);
                }
            });

            container.addEventListener('drop', function(e) {
                e.preventDefault();
                if (!dragEl) return;
                const items = [...container.querySelectorAll(':scope > [data-id]')];
                const order = items.map(function(el, idx) {
                    return { id: parseInt(el.dataset.id), sort_order: idx };
                });

                fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ order: order })
                });
            });

            container.addEventListener('dragend', function() {
                if (dragEl) dragEl.style.opacity = '1';
                dragEl = null;
            });
        }
    </script>
    @endpush
</x-admin-layout>
