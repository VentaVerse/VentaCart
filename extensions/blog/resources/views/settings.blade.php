<x-admin.shell title="Blog Settings">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.blog.index') }}">Blog</a>
                <span aria-hidden="true">/</span>
                <span>Settings</span>
            </div>
            <h1 class="v-head__title">Blog Settings</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.blog.index') }}" class="v-btn v-btn--secondary">Posts</a>
            <x-admin.extension-switch name="blog" />
        </div>
    </div>

    <x-admin.form :action="route('admin.blog.settings.update')" class="v-settle">
        <x-admin.panel title="Blog pages">
            <div class="v-narrow">
                <x-admin.field name="posts_per_page" label="Posts per page">
                    <input type="number" name="posts_per_page" min="4" max="48" class="v-control v-mono v-w-rate" value="{{ old('posts_per_page', $settings['posts_per_page'] ?? '12') }}">
                </x-admin.field>
                <div class="v-gap">
                    <x-admin.field name="default_view" label="Default view">
                        <select name="default_view" class="v-control">
                            <option value="grid" @selected(old('default_view', $settings['default_view'] ?? 'grid') === 'grid')>Grid</option>
                            <option value="list" @selected(old('default_view', $settings['default_view'] ?? '') === 'list')>List</option>
                        </select>
                    </x-admin.field>
                </div>
                <div class="v-gap">
                    <x-admin.field name="excerpt_length" label="Excerpt length (characters)">
                        <input type="number" name="excerpt_length" min="50" max="500" class="v-control v-mono v-w-rate" value="{{ old('excerpt_length', $settings['excerpt_length'] ?? '150') }}">
                    </x-admin.field>
                </div>
                <div class="v-gap">
                    <label class="v-toggle">
                        <input type="hidden" name="show_view_count" value="0">
                        <input type="checkbox" name="show_view_count" value="1" @checked(old('show_view_count', $settings['show_view_count'] ?? '0') === '1')>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Show view counts
                    </label>
                </div>
                <div class="v-gap">
                    <label class="v-toggle">
                        <input type="hidden" name="show_related_articles" value="0">
                        <input type="checkbox" name="show_related_articles" value="1" @checked(old('show_related_articles', $settings['show_related_articles'] ?? '0') === '1')>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Show related articles on product pages
                    </label>
                </div>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
