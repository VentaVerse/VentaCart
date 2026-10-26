<x-admin.shell title="Menus">
    <div class="v-head">
        <div class="v-head__body">
            {{-- The trail retraces the one way in. It used to climb back
                 through Extensions, Themes and Layout, none of which link
                 here, so every step up was a one way trip away from the page
                 you were on. --}}
            <div class="v-head__trail">
                <span>Content</span>
                <span aria-hidden="true">/</span>
                <span>Menus</span>
            </div>
            <h1 class="v-head__title">Menus</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ url('/') }}" target="_blank" rel="noopener" class="v-btn v-btn--sm">View store</a>
        </div>
    </div>

    <div class="v-menus">
        {{-- The arrangement: order and nesting, saved together by the
             header's Save. --}}
        <x-admin.form :action="route('admin.menus.arrange', $menu)">
            <div x-data="vMenuTree(@js($tree))">
                <input type="hidden" name="payload" :value="payload()">
                <x-admin.panel :title="$menu->name">
                    <p class="v-mtree__said" role="status" aria-live="polite" x-text="announcement"></p>

                    <ol class="v-mtree" x-show="items.length" @if (! count($tree)) x-cloak @endif>
                        <template x-for="row in flat()" :key="row.id">
                            <li class="v-mtree__row" :class="{ 'is-off': !row.enabled, 'is-child': row.depth > 0 }"
                                :style="'--depth:' + row.depth" :data-item="row.id"
                                :aria-label="name(row) + (row.parentName ? ', under ' + row.parentName : '') + (row.enabled ? '' : ', hidden')">
                                <div class="v-mtree__body">
                                    <span class="v-mtree__name">
                                        <b x-text="name(row)"></b>
                                        <i class="v-mtree__accent" x-show="row.accent" :style="'background:' + row.accent" aria-hidden="true"></i>
                                        <span class="v-badge v-badge--quiet" x-show="!row.enabled">Hidden</span>
                                        <span class="v-badge v-badge--quiet" x-show="row.mega">Mega menu</span>
                                    </span>
                                    <span class="v-mtree__meta">
                                        <span x-text="row.kind"></span><template x-if="row.where && row.where !== row.label"><span> &middot; <span :class="row.type === 'custom' ? 'v-mono' : ''" x-text="row.where"></span></span></template>
                                    </span>
                                </div>
                                <div class="v-mtree__acts">
                                    <span class="v-mtree__moves">
                                        <button type="button" data-act="up" @click="act(row.id, 'up')" :disabled="!can(row.id, 'up')" :aria-label="'Move ' + name(row) + ' up'" title="Move up"><x-admin.icon name="chevron-up" /></button>
                                        <button type="button" data-act="down" @click="act(row.id, 'down')" :disabled="!can(row.id, 'down')" :aria-label="'Move ' + name(row) + ' down'" title="Move down"><x-admin.icon name="chevron-down" /></button>
                                        <button type="button" data-act="in" @click="act(row.id, 'in')" :disabled="!can(row.id, 'in')" :aria-label="'Put ' + name(row) + ' under the item above'" title="Put under the item above"><x-admin.icon name="arrow-right" /></button>
                                        <button type="button" data-act="out" @click="act(row.id, 'out')" :disabled="!can(row.id, 'out')" :aria-label="'Move ' + name(row) + ' to the top level'" title="Move to the top level"><x-admin.icon name="arrow-left" /></button>
                                    </span>
                                    <a :href="'{{ route('admin.menus.items.edit', ['item' => '__ID__']) }}'.replace('__ID__', row.id)" class="v-btn v-btn--ghost v-btn--sm" :aria-label="'Edit ' + name(row)">Edit</a>
                                    <button type="button" class="v-row__x" :aria-label="'Delete ' + name(row)" title="Delete" @click="$dispatch('v-confirm', 'delete-item-' + row.id)">
                                        <x-admin.icon name="trash" />
                                    </button>
                                </div>
                            </li>
                        </template>
                    </ol>

                    <div class="v-empty" x-show="!items.length" @if (count($tree)) x-cloak @endif>
                        <p class="v-empty__title">No items in this menu yet</p>
                        <p class="v-empty__line">Add the first one with Add an item. It shows in the store header.</p>
                    </div>
                </x-admin.panel>
            </div>
        </x-admin.form>

        {{-- Adding an item: its own record, saved by its own button. --}}
        <x-admin.panel title="Add an item">
            <form method="POST" action="{{ route('admin.menus.items.store', $menu) }}" x-data="vMenuItemFields(@js(old('type', 'category')))">
                @csrf
                @include('admin.menus.fields', ['item' => null, 'categories' => $categories, 'pages' => $pages, 'system' => $system, 'types' => $types])
                <div class="v-gap">
                    <button type="submit" class="v-btn v-btn--primary">Add item</button>
                </div>
            </form>
        </x-admin.panel>
    </div>

    @foreach ($menu->items as $vItem)
        <x-admin.confirm name="delete-item-{{ $vItem->id }}" :title="'Delete ' . ($vItem->label ?: $types[$vItem->type]) . '?'"
            message="Items under it move up a level."
            confirm="Delete" :action="route('admin.menus.items.destroy', $vItem)" method="DELETE" />
    @endforeach

</x-admin.shell>
