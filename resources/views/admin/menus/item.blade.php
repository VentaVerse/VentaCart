<x-admin.shell :title="'Edit ' . ($item->label ?: $types[$item->type])">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <span>Content</span>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.menus') }}">Menus</a>
                <span aria-hidden="true">/</span>
                <span>{{ $item->label ?: $types[$item->type] }}</span>
            </div>
            <h1 class="v-head__title">{{ $item->label ?: $types[$item->type] }}</h1>
        </div>
    </div>

    <x-admin.form :action="route('admin.menus.items.update', $item)" method="PUT" class="v-settle">
        <div x-data="vMenuItemFields(@js(old('type', $item->type)))">
            <x-admin.panel title="Menu item">
                @include('admin.menus.fields', ['item' => $item, 'categories' => $categories, 'pages' => $pages, 'system' => $system, 'types' => $types])

                <div class="v-grid v-grid--2 v-gap">
                    {{-- The header menu is two levels deep, so an item that has
                         sub-items of its own stays at the top level. --}}
                    <x-admin.field name="parent_id" label="Under" :hint="$hasChildren ? 'It has sub-items of its own, so it stays at the top level.' : null">
                        <select name="parent_id" class="v-control" @disabled($hasChildren)>
                            <option value="">Top level</option>
                            @foreach ($parents as $vParent)
                                <option value="{{ $vParent->id }}" @selected((int) old('parent_id', $item->parent_id) === $vParent->id)>{{ $vParent->label ?: $types[$vParent->type] }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                    <div class="v-actrow v-menus__end">
                        <label class="v-toggle">
                            <input type="hidden" name="is_enabled" value="0">
                            <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $item->is_enabled))>
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            Shown in the menu
                        </label>
                    </div>
                </div>
            </x-admin.panel>
        </div>
    </x-admin.form>
</x-admin.shell>
