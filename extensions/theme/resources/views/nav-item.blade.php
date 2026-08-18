<x-admin.shell :title="'Edit ' . $item->label">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index') }}">Theme</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index', ['tab' => 'navigation']) }}">Navigation</a>
                <span aria-hidden="true">/</span>
                <span>{{ $item->label }}</span>
            </div>
            <h1 class="v-head__title">{{ $item->label }}</h1>
        </div>
    </div>

    <x-admin.form :action="route('admin.theme.nav.update', $item)" method="PUT" class="v-settle">
        <div x-data="{ navType: @js($item->type) }">
        <x-admin.panel title="Menu item">
            <div class="v-grid v-grid--2">
                <x-admin.field name="label" label="Label" required>
                    <input type="text" name="label" value="{{ old('label', $item->label) }}" required class="v-control">
                </x-admin.field>
                <x-admin.field name="type" label="Type" required>
                    <select name="type" x-model="navType" class="v-control">
                        <option value="category">Category</option>
                        <option value="custom">Custom link</option>
                    </select>
                </x-admin.field>
            </div>
            <div class="v-gap" x-show="navType === 'category'">
                <x-admin.field name="category_id" label="Category">
                    <select name="category_id" class="v-control">
                        <option value="">Select…</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $item->category_id) == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
            </div>
            <div class="v-gap" x-show="navType === 'custom'" x-cloak>
                <x-admin.field name="url" label="URL">
                    <input type="text" name="url" value="{{ old('url', $item->url) }}" placeholder="/blog or https://…" class="v-control v-mono">
                </x-admin.field>
            </div>
            <div class="v-gap v-actrow">
                <label class="v-toggle">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $item->is_enabled))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Shown in the menu
                </label>
                <label class="v-check">
                    <input type="hidden" name="new_tab" value="0">
                    <input type="checkbox" name="new_tab" value="1" @checked(old('new_tab', $item->new_tab))>
                    Open in a new tab
                </label>
            </div>
        </x-admin.panel>
        </div>
    </x-admin.form>
</x-admin.shell>
