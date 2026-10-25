<x-admin.shell :title="$attribute ? 'Edit Attribute' : 'New Attribute'" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.attributes.index') }}">Attributes</a>
                <span aria-hidden="true">/</span>
                <span>{{ $attribute->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $attribute ? $attribute->name : 'New attribute' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$attribute ? route('admin.attributes.update', $attribute) : route('admin.attributes.store')"
                  :method="$attribute ? 'PUT' : 'POST'" class="v-settle">
        <x-admin.panel title="Attribute">
            <div class="v-grid v-grid--2">
                <x-admin.field name="name" label="Name" required :hint="$attribute ? 'In filter addresses: ' . $attribute->slug : 'For example Origin, Weight or Lens width.'">
                    <input type="text" name="name" required maxlength="100" class="v-control" value="{{ old('name', $attribute?->name) }}">
                </x-admin.field>
                <x-admin.field name="group" label="Group" required hint="The heading it is listed under on the product page. Type a new name to start a group.">
                    <input type="text" name="group" required maxlength="100" list="attribute-groups" class="v-control" value="{{ old('group', $attribute?->group?->name) }}">
                    <datalist id="attribute-groups">
                        @foreach ($groupNames as $name)<option value="{{ $name }}"></option>@endforeach
                    </datalist>
                </x-admin.field>
                <x-admin.field name="sort_order" label="Sort order">
                    <input type="number" name="sort_order" min="0" max="9999" class="v-control" value="{{ old('sort_order', $attribute?->sort_order ?? 0) }}">
                </x-admin.field>
            </div>
            <div class="v-grid v-grid--2">
                <label class="v-check">
                    <input type="checkbox" name="is_filterable" value="1" @checked(old('is_filterable', $attribute?->is_filterable))>
                    <span>Shoppers can filter category pages by it</span>
                </label>
                <label class="v-check">
                    <input type="checkbox" name="show_on_card" value="1" @checked(old('show_on_card', $attribute?->show_on_card))>
                    <span>Show it on product cards</span>
                </label>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
