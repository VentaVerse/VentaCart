<x-admin.shell title="Attributes" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Attributes</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.attributes.create') }}" class="v-btn v-btn--primary">Add attribute</a>
        </div>
    </div>

    <div class="v-settle">
        @forelse ($groups as $group)
            <x-admin.panel :title="$group->name" flush>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th>Attribute</th>
                                <th>Shoppers can filter</th>
                                <th>On product cards</th>
                                <th class="v-cell-num">Products</th>
                                <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group->attributes as $attribute)
                                <tr>
                                    <td><a href="{{ route('admin.attributes.edit', $attribute) }}" class="v-rowlink">{{ $attribute->name }}</a></td>
                                    <td>{{ $attribute->is_filterable ? 'Yes' : 'No' }}</td>
                                    <td>{{ $attribute->show_on_card ? 'Yes' : 'No' }}</td>
                                    <td class="v-cell-num v-mono">{{ $attribute->values_count }}</td>
                                    <td class="v-cell-act">
                                        <a href="{{ route('admin.attributes.edit', $attribute) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-attribute-{{ $attribute->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>
        @empty
            <x-admin.panel>
                <div class="v-empty">
                    <p class="v-empty__title">No attributes yet</p>
                    <p class="v-empty__line">Attributes are the facts a product carries, such as origin, weight or lens width. Cards can show them and shoppers can filter by them.</p>
                    <a href="{{ route('admin.attributes.create') }}" class="v-btn v-btn--primary v-btn--sm">Create one</a>
                </div>
            </x-admin.panel>
        @endforelse

        @foreach ($groups as $group)
            @foreach ($group->attributes as $attribute)
                <x-admin.confirm name="delete-attribute-{{ $attribute->id }}" title="Delete {{ $attribute->name }}?"
                    message="This removes the attribute and its value from every product. This cannot be undone."
                    confirm="Delete" :action="route('admin.attributes.destroy', $attribute)" method="DELETE" />
            @endforeach
        @endforeach
    </div>
</x-admin.shell>
