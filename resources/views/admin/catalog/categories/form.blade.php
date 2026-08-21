<x-admin.shell :title="$category ? 'Edit Category' : 'Create Category'" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.categories.index') }}">Categories</a>
                <span aria-hidden="true">/</span>
                <span>{{ $category->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $category ? $category->name : 'Create category' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$category ? route('admin.categories.update', $category) : route('admin.categories.store')"
                   :method="$category ? 'PUT' : 'POST'" class="v-settle">
        <input type="hidden" name="store_id" value="1">

        <x-admin.panel title="Category">
            <div class="v-grid v-grid--2">
                <x-admin.field name="parent_id" label="Parent category">
                    <select name="parent_id" class="v-control">
                        <option value="">None (top-level)</option>
                        @foreach ($parentCategories as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id', $category?->parent_id) == $parent->id)>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v-control v-mono v-w-sort" value="{{ old('sort_order', $category?->sort_order ?? 0) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="name" label="Name" required>
                    <input type="text" name="name" required class="v-control" value="{{ old('name', $category?->name) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="description" label="Description">
                    <textarea name="description" rows="4" class="v-control">{{ old('description', $category?->description) }}</textarea>
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.media-picker name="image" :value="$category?->image"
                                      root="categories" label="Image" />
                @error('image') <span class="v-field__error">{{ $message }}</span> @enderror
            </div>
            <div class="v-gap">
                <label class="v-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    <span>Active</span>
                </label>
            </div>
        </x-admin.panel>

        <x-admin.panel title="SEO">
            <x-admin.field name="slug" label="Slug" hint="Leave blank to auto-generate from the name. Public URL: /category/{slug}">
                <input type="text" name="slug" class="v-control v-mono" value="{{ old('slug', $category?->slug) }}">
            </x-admin.field>
            <div class="v-gap v-grid v-grid--2">
                <x-admin.field name="meta_title" label="Meta title">
                    <input type="text" name="meta_title" maxlength="255" class="v-control" value="{{ old('meta_title', $category?->meta_title) }}">
                </x-admin.field>
                <x-admin.field name="meta_keywords" label="Meta keywords">
                    <input type="text" name="meta_keywords" maxlength="255" class="v-control" value="{{ old('meta_keywords', $category?->meta_keywords) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="meta_description" label="Meta description">
                    <textarea name="meta_description" rows="2" maxlength="500" class="v-control">{{ old('meta_description', $category?->meta_description) }}</textarea>
                </x-admin.field>
            </div>
            @include('admin.partials.seo_optimizer', ['entityType' => 'category', 'entityId' => $category?->id])
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
