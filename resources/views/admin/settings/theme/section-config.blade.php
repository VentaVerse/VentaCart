@php $config = $section->config ?? []; @endphp

@if ($section->type === 'banner')
    @php
        // The extension kernel decouples class-loadability from install
        // state, so class_exists alone no longer proves the table exists.
        $bannerGroups = (class_exists(\Extensions\Banners\Models\BannerGroup::class) && \Illuminate\Support\Facades\Schema::hasTable('banner_groups'))
            ? \Extensions\Banners\Models\BannerGroup::orderBy('name')->get()
            : collect();
    @endphp
    @if ($bannerGroups->isNotEmpty())
        <x-admin3.field name="config.banner_group" label="Banner group">
            <select name="config[banner_group]" class="v3-control">
                <option value="">Select a banner group</option>
                @foreach ($bannerGroups as $group)
                    <option value="{{ $group->slug }}" @selected(($config['banner_group'] ?? '') === $group->slug)>
                        {{ $group->name }} ({{ $group->slides()->count() }} slides)
                    </option>
                @endforeach
            </select>
        </x-admin3.field>
    @else
        <p class="v3-cell-quiet">Install the Banners extension to manage banner slides.</p>
    @endif

@elseif ($section->type === 'products')
    <x-admin3.field name="config.filter" label="Product filter">
        <select name="config[filter]" class="v3-control">
            @foreach (['new' => 'New arrivals', 'featured' => 'Featured', 'specials' => 'On sale / specials', 'category' => 'By category', 'brand' => 'By brand', 'best_sellers' => 'Best sellers'] as $v => $label)
                <option value="{{ $v }}" @selected(($config['filter'] ?? '') === $v)>{{ $label }}</option>
            @endforeach
        </select>
    </x-admin3.field>
    <div class="v3-gap">
        <x-admin3.field name="config.limit" label="Limit">
            <input type="number" name="config[limit]" value="{{ $config['limit'] ?? 12 }}" min="2" max="48" class="v3-control v3-mono v3-w-rate">
        </x-admin3.field>
    </div>
    <div class="v3-grid v3-grid--3 v3-gap">
        <x-admin3.field name="config.columns_mobile" label="Mobile columns">
            <input type="number" name="config[columns_mobile]" value="{{ $config['columns_mobile'] ?? 2 }}" min="1" max="3" class="v3-control v3-mono">
        </x-admin3.field>
        <x-admin3.field name="config.columns_tablet" label="Tablet columns">
            <input type="number" name="config[columns_tablet]" value="{{ $config['columns_tablet'] ?? 3 }}" min="2" max="4" class="v3-control v3-mono">
        </x-admin3.field>
        <x-admin3.field name="config.columns_desktop" label="Desktop columns">
            <input type="number" name="config[columns_desktop]" value="{{ $config['columns_desktop'] ?? 6 }}" min="3" max="8" class="v3-control v3-mono">
        </x-admin3.field>
    </div>

@elseif ($section->type === 'brands')
    <div class="v3-grid v3-grid--3">
        <x-admin3.field name="config.per_page" label="Per page">
            <input type="number" name="config[per_page]" value="{{ $config['per_page'] ?? 8 }}" min="4" max="20" class="v3-control v3-mono">
        </x-admin3.field>
        <div class="v3-addrow__opts">
            <label class="v3-check">
                <input type="checkbox" name="config[autoplay]" value="1" @checked($config['autoplay'] ?? false)>
                Autoplay
            </label>
        </div>
        <x-admin3.field name="config.speed" label="Speed (ms)">
            <input type="number" name="config[speed]" value="{{ $config['speed'] ?? 3000 }}" min="1000" step="500" class="v3-control v3-mono">
        </x-admin3.field>
    </div>

@elseif ($section->type === 'category_tabs')
    <x-admin3.field name="config.limit" label="Limit per tab">
        <input type="number" name="config[limit]" value="{{ $config['limit'] ?? 6 }}" min="2" max="24" class="v3-control v3-mono v3-w-rate">
    </x-admin3.field>
    <div class="v3-grid v3-grid--3 v3-gap">
        <x-admin3.field name="config.columns_mobile" label="Mobile columns">
            <input type="number" name="config[columns_mobile]" value="{{ $config['columns_mobile'] ?? 2 }}" min="1" max="3" class="v3-control v3-mono">
        </x-admin3.field>
        <x-admin3.field name="config.columns_tablet" label="Tablet columns">
            <input type="number" name="config[columns_tablet]" value="{{ $config['columns_tablet'] ?? 3 }}" min="2" max="4" class="v3-control v3-mono">
        </x-admin3.field>
        <x-admin3.field name="config.columns_desktop" label="Desktop columns">
            <input type="number" name="config[columns_desktop]" value="{{ $config['columns_desktop'] ?? 6 }}" min="3" max="8" class="v3-control v3-mono">
        </x-admin3.field>
    </div>

@elseif ($section->type === 'text_block')
    <x-admin3.field name="config.content" label="Content">
        <textarea name="config[content]" rows="4" class="v3-control">{{ $config['content'] ?? '' }}</textarea>
    </x-admin3.field>
    <div class="v3-gap">
        <x-admin3.field name="config.alignment" label="Alignment">
            <select name="config[alignment]" class="v3-control">
                <option value="left" @selected(($config['alignment'] ?? '') === 'left')>Left</option>
                <option value="center" @selected(($config['alignment'] ?? 'center') === 'center')>Center</option>
                <option value="right" @selected(($config['alignment'] ?? '') === 'right')>Right</option>
            </select>
        </x-admin3.field>
    </div>

@elseif ($section->type === 'blog_posts')
    <x-admin3.field name="config.limit" label="Limit">
        <input type="number" name="config[limit]" value="{{ $config['limit'] ?? 4 }}" min="2" max="12" class="v3-control v3-mono v3-w-rate">
    </x-admin3.field>
    <div class="v3-grid v3-grid--3 v3-gap">
        <x-admin3.field name="config.columns_mobile" label="Mobile columns">
            <input type="number" name="config[columns_mobile]" value="{{ $config['columns_mobile'] ?? 2 }}" min="1" max="3" class="v3-control v3-mono">
        </x-admin3.field>
        <x-admin3.field name="config.columns_tablet" label="Tablet columns">
            <input type="number" name="config[columns_tablet]" value="{{ $config['columns_tablet'] ?? 3 }}" min="2" max="4" class="v3-control v3-mono">
        </x-admin3.field>
        <x-admin3.field name="config.columns_desktop" label="Desktop columns">
            <input type="number" name="config[columns_desktop]" value="{{ $config['columns_desktop'] ?? 4 }}" min="3" max="8" class="v3-control v3-mono">
        </x-admin3.field>
    </div>

@elseif ($section->type === 'recently_viewed')
    <p class="v3-cell-quiet">Nothing to configure. The section appears once a visitor has viewed a product.</p>
@endif
