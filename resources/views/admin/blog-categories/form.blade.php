<x-admin.shell :title="$category ? 'Edit Blog Category' : 'New Blog Category'">
    <x-admin.page :title="$category ? 'Edit Blog Category' : 'New Blog Category'" :breadcrumb="[['label' => 'Content'], ['label' => 'Blog Categories', 'url' => route('admin.blog-categories.index')]]">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.blog-categories.index') }}">Back to Categories</x-admin.button>
        </x-slot:actions>

        <div class="max-w-xl">
            <x-admin.card>
                <form method="POST" action="{{ $category ? route('admin.blog-categories.update', $category) : route('admin.blog-categories.store') }}" data-v-form class="space-y-4">
                    @csrf
                    @if ($category)
                        @method('PUT')
                    @endif

                    <x-admin.field name="name" label="Name">
                        <x-admin.input type="text" name="name" required :value="$category?->name" />
                    </x-admin.field>

                    <x-admin.field name="slug" label="Slug" help="Auto-generated if empty">
                        <x-admin.input type="text" name="slug" :value="$category?->slug" />
                    </x-admin.field>

                    <x-admin.field name="sort_order" label="Sort Order">
                        <x-admin.input type="number" name="sort_order" class="w-24" min="0" :value="$category?->sort_order ?? 0" />
                    </x-admin.field>

                    <x-admin.form-actions>
                        <x-admin.button variant="secondary" href="{{ route('admin.blog-categories.index') }}">Cancel</x-admin.button>
                        <x-admin.button type="submit">{{ $category ? 'Update' : 'Create' }}</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
