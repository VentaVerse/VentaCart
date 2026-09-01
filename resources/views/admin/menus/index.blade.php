<x-admin.shell title="Menus">
    @push('head')
        @vite('resources/css/admin/layout-editor.css')
    @endpush

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

    <div class="v-settle v-menus">
        <div class="v-menus__body">
            {{-- the arrangement: one save scope --}}
            <x-admin.form :action="route('admin.menus.arrange', $menu)">
                <div x-data="menuTree(@js($tree))">
                    <input type="hidden" name="payload" :value="payload()">
                    <x-admin.panel :title="$menu->name">
                        <p class="v-lay__said" role="status" aria-live="polite" x-text="announcement" x-show="announcement" x-cloak></p>

                        <div class="v-tree" x-show="items.length" x-cloak>
                            <template x-for="(row, i) in flat()" :key="row.id">
                                <div class="v-tree__row" :class="{ 'is-off': !row.enabled, 'is-child': row.depth > 0 }"
                                     :style="'--depth:' + row.depth" tabindex="0" role="group" :aria-label="row.label || row.kind">
                                    <span class="v-tree__grip" aria-hidden="true">⣿</span>
                                    <span class="v-tree__body">
                                        <span class="v-tree__name">
                                            <b x-text="row.label || row.kind"></b>
                                            <span class="v-tag" x-text="row.kind"></span>
                                            <span class="v-tag" x-show="row.mega">Mega</span>
                                            <i class="v-tree__accent" x-show="row.accent" :style="'background:' + row.accent" aria-hidden="true"></i>
                                        </span>
                                        <span class="v-tree__where mono" x-text="row.where"></span>
                                    </span>
                                    <span class="v-tree__acts">
                                        <button type="button" @click="move(row.id, -1)" :disabled="!canMove(row.id, -1)" aria-label="Move up" title="Move up">&uarr;</button>
                                        <button type="button" @click="move(row.id, 1)" :disabled="!canMove(row.id, 1)" aria-label="Move down" title="Move down">&darr;</button>
                                        <button type="button" @click="indent(row.id)" :disabled="!canIndent(row.id)" aria-label="Make a sub-item" title="Make a sub-item">&rarr;</button>
                                        <button type="button" @click="outdent(row.id)" :disabled="row.depth === 0" aria-label="Move up a level" title="Move up a level">&larr;</button>
                                        <a :href="'{{ route('admin.menus.items.edit', ['item' => '__ID__']) }}'.replace('__ID__', row.id)" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                        <button type="button" class="v-row__x" :aria-label="'Delete ' + (row.label || row.kind)" @click="$dispatch('v-confirm', 'delete-item-' + row.id)">
                                            <x-admin.icon name="trash" />
                                        </button>
                                    </span>
                                </div>
                            </template>
                        </div>

                        <p class="v-slot__empty" x-show="!items.length">Nothing in this menu yet</p>
                    </x-admin.panel>
                </div>
            </x-admin.form>

            {{-- adding an item: its own record --}}
            <x-admin.panel title="Add an item">
                <form method="POST" action="{{ route('admin.menus.items.store', $menu) }}" x-data="{ kind: @js(old('type', 'category')) }">
                    @csrf
                    @include('admin.menus.fields', ['item' => null, 'categories' => $categories, 'pages' => $pages, 'system' => $system, 'types' => $types])
                    <div class="v-gap v-actrow">
                        <button type="submit" class="v-btn v-btn--primary">Add item</button>
                    </div>
                </form>
            </x-admin.panel>
        </div>
    </div>

    @foreach ($menu->items as $vItem)
        <x-admin.confirm name="delete-item-{{ $vItem->id }}" :title="'Delete ' . ($vItem->label ?: $types[$vItem->type]) . '?'"
            message="Items under it move up a level."
            confirm="Delete" :action="route('admin.menus.items.destroy', $vItem)" method="DELETE" />
    @endforeach

    @push('scripts')
        <script>
            /**
             * A menu as a tree the merchant arranges: order and nesting are
             * this page's save; everything about an item is its own form.
             */
            function menuTree(seed) {
                let root = null;
                return {
                    items: seed,
                    announcement: '',
                    sayTimer: null,

                    init() { root = this.$root ?? this.$el; },

                    /** The tree as rows with depth, for drawing. */
                    flat() {
                        const out = [];
                        const walk = (list, depth) => list.forEach((n) => { out.push({ ...n, depth }); walk(n.children, depth + 1); });
                        walk(this.items, 0);
                        return out;
                    },

                    /** Where a node sits: its siblings list and index. */
                    locate(id, list = this.items, parent = null) {
                        for (let i = 0; i < list.length; i++) {
                            if (list[i].id === id) return { list, i, parent };
                            const found = this.locate(id, list[i].children, list[i]);
                            if (found) return found;
                        }
                        return null;
                    },

                    canMove(id, by) {
                        const at = this.locate(id);
                        return !!at && at.i + by >= 0 && at.i + by < at.list.length;
                    },

                    move(id, by) {
                        if (!this.canMove(id, by)) return;
                        const at = this.locate(id);
                        const [node] = at.list.splice(at.i, 1);
                        at.list.splice(at.i + by, 0, node);
                        this.commit((node.label || node.kind) + ' moved ' + (by < 0 ? 'up' : 'down'));
                    },

                    /** Under the item above it, one level at most: a header menu is two levels deep. */
                    canIndent(id) {
                        const at = this.locate(id);
                        return !!at && at.i > 0 && at.parent === null;
                    },

                    indent(id) {
                        if (!this.canIndent(id)) return;
                        const at = this.locate(id);
                        const [node] = at.list.splice(at.i, 1);
                        const host = at.list[at.i - 1];
                        // Its own children come up a level; two levels is the header's depth.
                        host.children.push(node, ...node.children);
                        node.children = [];
                        this.commit((node.label || node.kind) + ' is now under ' + (host.label || host.kind));
                    },

                    outdent(id) {
                        const at = this.locate(id);
                        if (!at || at.parent === null) return;
                        const [node] = at.list.splice(at.i, 1);
                        const up = this.locate(at.parent.id);
                        up.list.splice(up.i + 1, 0, node);
                        this.commit((node.label || node.kind) + ' moved up a level');
                    },

                    commit(message) {
                        this.items = [...this.items];
                        this.say(message + '. Save to keep it.');
                        (root ?? this.$root).dispatchEvent(new Event('change', { bubbles: true }));
                    },

                    say(message) {
                        this.announcement = message;
                        clearTimeout(this.sayTimer);
                        this.sayTimer = setTimeout(() => { this.announcement = ''; }, 4000);
                    },

                    payload() {
                        const out = [];
                        const walk = (list, parentId) => list.forEach((n) => { out.push({ id: n.id, parent_id: parentId }); walk(n.children, n.id); });
                        walk(this.items, null);
                        return JSON.stringify({ items: out });
                    },
                };
            }
        </script>
    @endpush
</x-admin.shell>
