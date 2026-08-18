<x-admin3.shell :title="$category ? 'Edit Category' : 'Create Category'" active="catalog">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.categories.index') }}">Categories</a>
                <span aria-hidden="true">/</span>
                <span>{{ $category->name ?? 'New' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $category ? $category->name : 'Create category' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$category ? route('admin.categories.update', $category) : route('admin.categories.store')"
                   :method="$category ? 'PUT' : 'POST'" class="v3-settle">
        <input type="hidden" name="store_id" value="1">

        <x-admin3.panel title="Category">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="parent_id" label="Parent category">
                    <select name="parent_id" class="v3-control">
                        <option value="">None (top-level)</option>
                        @foreach ($parentCategories as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id', $category?->parent_id) == $parent->id)>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </x-admin3.field>
                <x-admin3.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v3-control v3-mono v3-w-sort" value="{{ old('sort_order', $category?->sort_order ?? 0) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="name" label="Name" required>
                    <input type="text" name="name" required class="v3-control" value="{{ old('name', $category?->name) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="description" label="Description">
                    <textarea name="description" rows="4" class="v3-control">{{ old('description', $category?->description) }}</textarea>
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                @include('admin.partials.v3_single_image_picker', [
                    'fieldName' => 'image',
                    'currentImage' => $category?->image,
                    'root' => 'categories',
                    'defaultFolder' => 'Categories',
                ])
                @error('image') <span class="v3-field__error">{{ $message }}</span> @enderror
            </div>
            <div class="v3-gap">
                <label class="v3-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    <span>Active</span>
                </label>
            </div>
        </x-admin3.panel>

        <x-admin3.panel title="SEO">
            <x-admin3.field name="slug" label="Slug" hint="Leave blank to auto-generate from the name. Public URL: /category/{slug}">
                <input type="text" name="slug" class="v3-control v3-mono" value="{{ old('slug', $category?->slug) }}">
            </x-admin3.field>
            <div class="v3-gap v3-grid v3-grid--2">
                <x-admin3.field name="meta_title" label="Meta title">
                    <input type="text" name="meta_title" maxlength="255" class="v3-control" value="{{ old('meta_title', $category?->meta_title) }}">
                </x-admin3.field>
                <x-admin3.field name="meta_keywords" label="Meta keywords">
                    <input type="text" name="meta_keywords" maxlength="255" class="v3-control" value="{{ old('meta_keywords', $category?->meta_keywords) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="meta_description" label="Meta description">
                    <textarea name="meta_description" rows="2" maxlength="500" class="v3-control">{{ old('meta_description', $category?->meta_description) }}</textarea>
                </x-admin3.field>
            </div>
            @include('admin.partials.v3_seo_optimizer', ['entityType' => 'category', 'entityId' => $category?->id])
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.shell>
