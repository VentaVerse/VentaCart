<x-admin3.shell :title="$tag ? 'Edit Tag' : 'New Tag'" active="catalog">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.tags.index') }}">Tags</a>
                <span aria-hidden="true">/</span>
                <span>{{ $tag->name ?? 'New' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $tag ? $tag->name : 'New tag' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$tag ? route('admin.tags.update', $tag) : route('admin.tags.store')"
                   :method="$tag ? 'PUT' : 'POST'" class="v3-settle">
        <x-admin3.panel title="Tag">
            <x-admin3.field name="name" label="Name" required :hint="$tag ? 'Slug: ' . $tag->slug : null">
                <input type="text" name="name" required class="v3-control" value="{{ old('name', $tag?->name) }}">
            </x-admin3.field>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.shell>
