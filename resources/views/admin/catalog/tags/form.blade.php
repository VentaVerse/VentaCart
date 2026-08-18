<x-admin.shell :title="$tag ? 'Edit Tag' : 'New Tag'" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.tags.index') }}">Tags</a>
                <span aria-hidden="true">/</span>
                <span>{{ $tag->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $tag ? $tag->name : 'New tag' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$tag ? route('admin.tags.update', $tag) : route('admin.tags.store')"
                   :method="$tag ? 'PUT' : 'POST'" class="v-settle">
        <x-admin.panel title="Tag">
            <x-admin.field name="name" label="Name" required :hint="$tag ? 'Slug: ' . $tag->slug : null">
                <input type="text" name="name" required class="v-control" value="{{ old('name', $tag?->name) }}">
            </x-admin.field>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
