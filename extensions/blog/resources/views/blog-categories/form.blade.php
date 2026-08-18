<x-admin.shell :title="$category ? 'Edit Blog Category' : 'New Blog Category'">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.blog.index') }}">Blog</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.blog-categories.index') }}">Categories</a>
                <span aria-hidden="true">/</span>
                <span>{{ $category->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $category ? $category->name : 'New category' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$category ? route('admin.blog-categories.update', $category) : route('admin.blog-categories.store')"
                   :method="$category ? 'PUT' : 'POST'" class="v-settle">
        <x-admin.panel title="Category">
            <x-admin.field name="name" label="Name" required>
                <input type="text" name="name" required class="v-control" value="{{ old('name', $category?->name) }}">
            </x-admin.field>
            <div class="v-gap">
                <x-admin.field name="slug" label="Slug" hint="Leave blank to auto-generate from the name.">
                    <input type="text" name="slug" class="v-control v-mono" value="{{ old('slug', $category?->slug) }}">
                </x-admin.field>
            </div>
            <div class="v-gap">
                <x-admin.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v-control v-mono v-w-sort" value="{{ old('sort_order', $category?->sort_order ?? 0) }}">
                </x-admin.field>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
