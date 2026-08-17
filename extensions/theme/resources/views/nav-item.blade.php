<x-admin3.shell :title="'Edit ' . $item->label">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index') }}">Theme</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index', ['tab' => 'navigation']) }}">Navigation</a>
                <span aria-hidden="true">/</span>
                <span>{{ $item->label }}</span>
            </div>
            <h1 class="v3-head__title">{{ $item->label }}</h1>
        </div>
    </div>

    <x-admin3.form :action="route('admin.theme.nav.update', $item)" method="PUT" class="v3-settle">
        <div x-data="{ navType: @js($item->type) }">
        <x-admin3.panel title="Menu item">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="label" label="Label" required>
                    <input type="text" name="label" value="{{ old('label', $item->label) }}" required class="v3-control">
                </x-admin3.field>
                <x-admin3.field name="type" label="Type" required>
                    <select name="type" x-model="navType" class="v3-control">
                        <option value="category">Category</option>
                        <option value="custom">Custom link</option>
                    </select>
                </x-admin3.field>
            </div>
            <div class="v3-gap" x-show="navType === 'category'">
                <x-admin3.field name="category_id" label="Category">
                    <select name="category_id" class="v3-control">
                        <option value="">Select…</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $item->category_id) == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </x-admin3.field>
            </div>
            <div class="v3-gap" x-show="navType === 'custom'" x-cloak>
                <x-admin3.field name="url" label="URL">
                    <input type="text" name="url" value="{{ old('url', $item->url) }}" placeholder="/blog or https://…" class="v3-control v3-mono">
                </x-admin3.field>
            </div>
            <div class="v3-gap v3-actrow">
                <label class="v3-toggle">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $item->is_enabled))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Shown in the menu
                </label>
                <label class="v3-check">
                    <input type="hidden" name="new_tab" value="0">
                    <input type="checkbox" name="new_tab" value="1" @checked(old('new_tab', $item->new_tab))>
                    Open in a new tab
                </label>
            </div>
        </x-admin3.panel>
        </div>
    </x-admin3.form>
</x-admin3.shell>
