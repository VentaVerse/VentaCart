<x-admin3.shell :title="$category ? 'Edit Blog Category' : 'New Blog Category'">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.blog.index') }}">Blog</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.blog-categories.index') }}">Categories</a>
                <span aria-hidden="true">/</span>
                <span>{{ $category->name ?? 'New' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $category ? $category->name : 'New category' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$category ? route('admin.blog-categories.update', $category) : route('admin.blog-categories.store')"
                   :method="$category ? 'PUT' : 'POST'" class="v3-settle">
        <x-admin3.panel title="Category">
            <x-admin3.field name="name" label="Name" required>
                <input type="text" name="name" required class="v3-control" value="{{ old('name', $category?->name) }}">
            </x-admin3.field>
            <div class="v3-gap">
                <x-admin3.field name="slug" label="Slug" hint="Leave blank to auto-generate from the name.">
                    <input type="text" name="slug" class="v3-control v3-mono" value="{{ old('slug', $category?->slug) }}">
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <x-admin3.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" class="v3-control v3-mono v3-w-sort" value="{{ old('sort_order', $category?->sort_order ?? 0) }}">
                </x-admin3.field>
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.shell>
