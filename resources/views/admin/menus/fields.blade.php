{{-- The fields an item needs, by kind. Shared by the add panel and the item
     editor; the parent's vMenuItemFields holds the chosen kind in `kind`. --}}
@props(['item' => null, 'categories' => [], 'pages' => [], 'system' => [], 'types' => []])
@php
    $vCfg = $item?->config ?? [];
@endphp
<div class="v-grid v-grid--2">
    <x-admin.field name="type" label="Kind" required>
        <select name="type" x-model="kind" class="v-control">
            @foreach ($types as $vKey => $vLabel)
                <option value="{{ $vKey }}" @selected(old('type', $item?->type ?? 'category') === $vKey)>{{ $vLabel }}</option>
            @endforeach
        </select>
    </x-admin.field>
    <div x-show="kind !== 'separator'">
        <x-admin.field name="label" label="Label" required>
            <input type="text" name="label" x-ref="label" value="{{ old('label', $item?->label) }}" class="v-control" maxlength="255">
        </x-admin.field>
    </div>
</div>

<div class="v-gap" x-show="kind === 'category'" x-cloak>
    <x-admin.field name="category_id" label="Category">
        <select name="category_id" class="v-control" @change="fillLabel($event)">
            <option value="">Choose&hellip;</option>
            @foreach ($categories as $vCat)
                <option value="{{ $vCat['id'] }}" @selected((int) old('category_id', $item?->category_id) === $vCat['id'])>{{ $vCat['label'] }}</option>
            @endforeach
        </select>
    </x-admin.field>
    <div class="v-grid v-grid--2 v-gap">
        <x-admin.field name="dropdown" label="Dropdown">
            <select name="dropdown" class="v-control">
                <option value="subcategories" @selected(old('dropdown', $vCfg['dropdown'] ?? 'subcategories') === 'subcategories')>Subcategories</option>
                <option value="none" @selected(old('dropdown', $vCfg['dropdown'] ?? '') === 'none')>None</option>
            </select>
        </x-admin.field>
        <x-admin.field name="depth" label="Levels shown">
            <select name="depth" class="v-control">
                @foreach ([1, 2, 3] as $vD)
                    <option value="{{ $vD }}" @selected((int) old('depth', $vCfg['depth'] ?? 2) === $vD)>{{ $vD }}</option>
                @endforeach
            </select>
        </x-admin.field>
    </div>
    <div class="v-grid v-grid--2 v-gap">
        <x-admin.field name="mega" label="Mega menu" hint="Subcategories in columns with room for a banner and a brands column.">
            <select name="mega" class="v-control">
                <option value="no" @selected(old('mega', $vCfg['mega'] ?? 'no') === 'no')>Off</option>
                <option value="yes" @selected(old('mega', $vCfg['mega'] ?? 'no') === 'yes')>On</option>
            </select>
        </x-admin.field>
        <x-admin.field name="brands" label="Brands column">
            <select name="brands" class="v-control">
                <option value="no" @selected(old('brands', $vCfg['brands'] ?? 'no') === 'no')>Off</option>
                <option value="yes" @selected(old('brands', $vCfg['brands'] ?? 'no') === 'yes')>On</option>
            </select>
        </x-admin.field>
    </div>
    <div class="v-gap">
        <x-admin.media-picker name="banner" :value="old('banner', $vCfg['banner'] ?? '')" root="banners" label="Mega menu banner" trigger="Choose image" can="upload,url" />
    </div>
    <div class="v-gap">
        <x-admin.field name="banner_url" label="Banner link" hint="Where the banner leads: a path such as /sale, or a full https:// address.">
            <input type="text" name="banner_url" value="{{ old('banner_url', $vCfg['banner_url'] ?? '') }}" class="v-control v-mono" maxlength="500">
        </x-admin.field>
    </div>
</div>

<div class="v-gap" x-show="kind === 'page'" x-cloak>
    <x-admin.field name="page_id" label="Page">
        <select name="page_id" class="v-control" @change="fillLabel($event)">
            <option value="">Choose&hellip;</option>
            @foreach ($pages as $vPage)
                <option value="{{ $vPage->id }}" @selected((int) old('page_id', $item?->page_id) === $vPage->id)>{{ $vPage->title }}</option>
            @endforeach
        </select>
    </x-admin.field>
</div>

<div class="v-gap" x-show="kind === 'custom'" x-cloak>
    <x-admin.field name="url" label="Link" hint="A path such as /sale, or a full https:// address.">
        <input type="text" name="url" value="{{ old('url', $item?->url) }}" class="v-control v-mono" maxlength="255" placeholder="/sale">
    </x-admin.field>
</div>

<div class="v-gap" x-show="kind === 'system'" x-cloak>
    <x-admin.field name="system_page" label="Store page">
        <select name="system_page" class="v-control" @change="fillLabel($event)">
            @foreach ($system as $vKey => $vSys)
                <option value="{{ $vKey }}" @selected(old('system_page', $vCfg['page'] ?? '') === $vKey)>{{ $vSys['label'] }}</option>
            @endforeach
        </select>
    </x-admin.field>
</div>

<div class="v-gap" x-show="kind === 'html'" x-cloak>
    {{-- Printed on the storefront unescaped, so only a super admin may change
         it. Anyone else sees it read-only rather than meeting a refusal after
         saving. --}}
    @can(\App\Support\AdminPermissions::STOREFRONT_CODE)
        <x-admin.field name="html" label="HTML">
            <textarea name="html" rows="6" class="v-control v-mono">{{ old('html', $vCfg['html'] ?? '') }}</textarea>
        </x-admin.field>
    @else
        <x-admin.field name="html" label="HTML" hint="Only a super admin can change custom HTML.">
            <textarea name="html" rows="6" class="v-control v-mono" readonly>{{ $vCfg['html'] ?? '' }}</textarea>
        </x-admin.field>
    @endcan
</div>

<div class="v-grid v-grid--2 v-gap" x-show="kind !== 'separator' && kind !== 'title'">
    <x-admin.field name="accent" label="Accent color" hint="A color of its own for this item, such as a red Sale.">
        <span class="v-namerow" x-data="{ accent: @js((string) old('accent', $vCfg['accent'] ?? '')) }">
            <input type="color" value="{{ old('accent', $vCfg['accent'] ?? '') ?: '#C0873A' }}" class="v-swatch" :class="{ 'is-empty': !/^#[0-9a-fA-F]{6}$/.test(accent) }"
                   aria-label="Pick an accent color" x-effect="if (/^#[0-9a-fA-F]{6}$/.test(accent)) $el.value = accent.toLowerCase()"
                   @input="accent = $event.target.value.toUpperCase()">
            <input type="text" name="accent" x-model="accent" class="v-control v-mono v-w-sort" maxlength="7" placeholder="None" aria-label="Accent color as a hex code">
            <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-show="accent !== ''" @click="accent = ''">Clear</button>
        </span>
    </x-admin.field>
    <div class="v-actrow v-menus__end">
        <label class="v-check">
            <input type="hidden" name="new_tab" value="0">
            <input type="checkbox" name="new_tab" value="1" @checked(old('new_tab', $item?->new_tab))>
            Open in a new tab
        </label>
    </div>
</div>
