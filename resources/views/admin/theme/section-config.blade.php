@php $config = $section->config ?? []; @endphp

@if ($section->type === 'banner')
    @php
        // The extension kernel decouples class-loadability from install
        // state (routes/DB rows can be absent even once a class has been
        // autoloaded once), so class_exists alone no longer proves the
        // underlying table exists — guard it explicitly, same as
        // BannerServiceProvider's own boot-time checks do.
        $bannerGroups = (class_exists(\Extensions\Banners\Models\BannerGroup::class) && \Illuminate\Support\Facades\Schema::hasTable('banner_groups'))
            ? \Extensions\Banners\Models\BannerGroup::orderBy('name')->get()
            : collect();
    @endphp
    <div class="space-y-3">
        @if ($bannerGroups->isNotEmpty())
            <x-admin.field name="config[banner_group]" label="Banner Group">
                <x-admin.select name="config[banner_group]">
                    <option value="">— Select a banner group —</option>
                    @foreach ($bannerGroups as $group)
                        <option value="{{ $group->slug }}" {{ ($config['banner_group'] ?? '') === $group->slug ? 'selected' : '' }}>
                            {{ $group->name }} ({{ $group->slides()->count() }} slides)
                        </option>
                    @endforeach
                </x-admin.select>
            </x-admin.field>
            <p class="text-xs text-v-muted">Slides, autoplay, and dimensions are configured in the Banner Group itself.</p>
        @else
            <p class="text-[13px] text-v-secondary">Install the Banners extension to manage banner slides.</p>
        @endif
    </div>

@elseif ($section->type === 'products')
    <div class="space-y-3">
        <x-admin.field name="config[filter]" label="Product Filter">
            <x-admin.select name="config[filter]">
                <option value="new" {{ ($config['filter'] ?? '') === 'new' ? 'selected' : '' }}>New Arrivals</option>
                <option value="featured" {{ ($config['filter'] ?? '') === 'featured' ? 'selected' : '' }}>Featured</option>
                <option value="specials" {{ ($config['filter'] ?? '') === 'specials' ? 'selected' : '' }}>On Sale / Specials</option>
                <option value="category" {{ ($config['filter'] ?? '') === 'category' ? 'selected' : '' }}>By Category</option>
                <option value="brand" {{ ($config['filter'] ?? '') === 'brand' ? 'selected' : '' }}>By Brand</option>
                <option value="best_sellers" {{ ($config['filter'] ?? '') === 'best_sellers' ? 'selected' : '' }}>Best Sellers</option>
            </x-admin.select>
        </x-admin.field>
        <x-admin.field name="config[limit]" label="Limit">
            <x-admin.input type="number" name="config[limit]" :value="$config['limit'] ?? 12" min="2" max="48" class="w-24" />
        </x-admin.field>
        <div class="grid grid-cols-3 gap-3">
            <x-admin.field name="config[columns_mobile]" label="Mobile Cols">
                <x-admin.input type="number" name="config[columns_mobile]" :value="$config['columns_mobile'] ?? 2" min="1" max="3" />
            </x-admin.field>
            <x-admin.field name="config[columns_tablet]" label="Tablet Cols">
                <x-admin.input type="number" name="config[columns_tablet]" :value="$config['columns_tablet'] ?? 3" min="2" max="4" />
            </x-admin.field>
            <x-admin.field name="config[columns_desktop]" label="Desktop Cols">
                <x-admin.input type="number" name="config[columns_desktop]" :value="$config['columns_desktop'] ?? 6" min="3" max="8" />
            </x-admin.field>
        </div>
    </div>

@elseif ($section->type === 'brands')
    <div class="space-y-3">
        <div class="grid grid-cols-3 gap-3">
            <x-admin.field name="config[per_page]" label="Per Page">
                <x-admin.input type="number" name="config[per_page]" :value="$config['per_page'] ?? 8" min="4" max="20" />
            </x-admin.field>
            <div class="flex items-end pb-1.5">
                <x-admin.checkbox name="config[autoplay]" :checked="$config['autoplay'] ?? false" label="Autoplay" />
            </div>
            <x-admin.field name="config[speed]" label="Speed (ms)">
                <x-admin.input type="number" name="config[speed]" :value="$config['speed'] ?? 3000" min="1000" step="500" />
            </x-admin.field>
        </div>
    </div>

@elseif ($section->type === 'category_tabs')
    <div class="space-y-3">
        <x-admin.field name="config[limit]" label="Limit per tab">
            <x-admin.input type="number" name="config[limit]" :value="$config['limit'] ?? 6" min="2" max="24" class="w-24" />
        </x-admin.field>
        <div class="grid grid-cols-3 gap-3">
            <x-admin.field name="config[columns_mobile]" label="Mobile Cols">
                <x-admin.input type="number" name="config[columns_mobile]" :value="$config['columns_mobile'] ?? 2" min="1" max="3" />
            </x-admin.field>
            <x-admin.field name="config[columns_tablet]" label="Tablet Cols">
                <x-admin.input type="number" name="config[columns_tablet]" :value="$config['columns_tablet'] ?? 3" min="2" max="4" />
            </x-admin.field>
            <x-admin.field name="config[columns_desktop]" label="Desktop Cols">
                <x-admin.input type="number" name="config[columns_desktop]" :value="$config['columns_desktop'] ?? 6" min="3" max="8" />
            </x-admin.field>
        </div>
        <p class="text-xs text-v-muted">Categories for tabs will be selectable after the category selector is built.</p>
    </div>

@elseif ($section->type === 'text_block')
    <div class="space-y-3">
        <x-admin.field name="config[content]" label="Content">
            <x-admin.textarea name="config[content]" rows="4" :value="$config['content'] ?? ''" />
        </x-admin.field>
        <x-admin.field name="config[alignment]" label="Alignment">
            <x-admin.select name="config[alignment]">
                <option value="left" {{ ($config['alignment'] ?? '') === 'left' ? 'selected' : '' }}>Left</option>
                <option value="center" {{ ($config['alignment'] ?? 'center') === 'center' ? 'selected' : '' }}>Center</option>
                <option value="right" {{ ($config['alignment'] ?? '') === 'right' ? 'selected' : '' }}>Right</option>
            </x-admin.select>
        </x-admin.field>
    </div>

@elseif ($section->type === 'blog_posts')
    <div class="space-y-3">
        <x-admin.field name="config[limit]" label="Limit">
            <x-admin.input type="number" name="config[limit]" :value="$config['limit'] ?? 4" min="2" max="12" class="w-24" />
        </x-admin.field>
        <div class="grid grid-cols-3 gap-3">
            <x-admin.field name="config[columns_mobile]" label="Mobile Cols">
                <x-admin.input type="number" name="config[columns_mobile]" :value="$config['columns_mobile'] ?? 2" min="1" max="3" />
            </x-admin.field>
            <x-admin.field name="config[columns_tablet]" label="Tablet Cols">
                <x-admin.input type="number" name="config[columns_tablet]" :value="$config['columns_tablet'] ?? 3" min="2" max="4" />
            </x-admin.field>
            <x-admin.field name="config[columns_desktop]" label="Desktop Cols">
                <x-admin.input type="number" name="config[columns_desktop]" :value="$config['columns_desktop'] ?? 4" min="3" max="8" />
            </x-admin.field>
        </div>
    </div>

@elseif ($section->type === 'recently_viewed')
    <div>
        <p class="text-xs text-v-muted">Shows products the visitor has recently browsed. Data comes from the browser — no configuration needed. The section only appears when the visitor has viewed at least one product.</p>
    </div>
@endif
