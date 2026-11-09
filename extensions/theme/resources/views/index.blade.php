<x-admin.shell title="Theme">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Theme</span>
            </div>
            <h1 class="v-head__title">Theme</h1>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="theme" />
        </div>
    </div>

    @php
        // An unknown tab falls back to the first, so a stale link never
        // opens a page with every pane hidden.
        $vTabs = ['header', 'footer'];
        $vTab = session('tab', request('tab', 'header'));
        $vTab = in_array($vTab, $vTabs, true) ? $vTab : 'header';
    @endphp

    <div x-data="themeSections(@js($vTab))"
         class="v-gap-lg">

        <div class="v-modgrid">
        <nav class="v-snav" aria-label="Theme sections">
            @php
                $vThemeNav = ['header' => ['Header', 'menu'], 'footer' => ['Footer', 'layers']];
                // Arranging pages lives in Layout, one entry per installed theme.
                $vInstalledThemes = \App\Http\Middleware\EditTheme::rideable(app(\App\Services\ThemeService::class));
                $vLiveTheme = app(\App\Services\ThemeService::class)->getActiveTheme();
                $vThemeLinks = count($vInstalledThemes) > 1
                    ? collect($vInstalledThemes)->map(fn ($t, $vSlug) => [
                        'label' => 'Arrange pages: ' . ($t['name'] ?? $vSlug),
                        'url' => route('admin.layout', $vSlug === $vLiveTheme ? [] : ['theme' => $vSlug]),
                    ])->values()->all()
                    : [['label' => 'Arrange pages', 'url' => route('admin.layout')]];
            @endphp
            @foreach ($vThemeNav as $tid => [$tlabel, $ticon])
                <button type="button" class="v-snav__item" :aria-current="tab === @js($tid) ? 'page' : false" @click="pick(@js($tid))">
                    <x-admin.icon :name="$ticon" />
                    {{ $tlabel }}
                </button>
            @endforeach
            <div class="v-snav__group">Pages</div>
            @foreach ($vThemeLinks as $vLink)
                <a class="v-snav__item v-snav__item--away" href="{{ $vLink['url'] }}">
                    <x-admin.icon name="layers" />
                    {{ $vLink['label'] }}
                </a>
            @endforeach
        </nav>
        <div class="v-modmain">

        <x-admin.form :action="route('admin.theme.settings.update')" :save-only-when-dirty="true">

            {{-- ========== HEADER ========== --}}
            <div x-show="tab === 'header'" x-cloak>
                <x-admin.panel title="Announcement bar">
                    <label class="v-toggle">
                        <input type="hidden" name="groups[header][announcement_enabled]" value="0">
                        <input type="checkbox" name="groups[header][announcement_enabled]" value="1"
                               @checked(($settings['header']['announcement_enabled'] ?? '0') === '1')>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Show the announcement bar
                    </label>
                    <div class="v-grid v-grid--2 v-gap">
                        <x-admin.field name="groups.header.announcement_text" label="Announcement text">
                            <input type="text" name="groups[header][announcement_text]" class="v-control" value="{{ $settings['header']['announcement_text'] ?? '' }}">
                        </x-admin.field>
                        <x-admin.field name="groups.header.announcement_link" label="Announcement link">
                            <input type="text" name="groups[header][announcement_link]" class="v-control v-mono" placeholder="/page-url or https://…" value="{{ $settings['header']['announcement_link'] ?? '' }}">
                        </x-admin.field>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Top bar links">
                    <div x-data="{ links: @js(json_decode($settings['header']['topbar_links'] ?? '[]', true) ?: []) }">
                        <template x-for="(link, idx) in links" :key="idx">
                            <div class="v-row">
                                <input type="text" x-model="link.label" aria-label="Topbar link label" placeholder="Label" class="v-control">
                                <input type="text" x-model="link.url" aria-label="Topbar link URL" placeholder="/url" class="v-control v-mono">
                                <button type="button" class="v-row__x" @click="links.splice(idx, 1); touch()" aria-label="Remove link">
                                    <x-admin.icon name="trash" />
                                </button>
                            </div>
                        </template>
                        <div class="v-gap">
                            <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="links.push({label:'',url:''}); touch()">
                                <x-admin.icon name="plus" /> Add link
                            </button>
                        </div>
                        <input type="hidden" name="groups[header][topbar_links]" :value="JSON.stringify(links)">
                    </div>
                </x-admin.panel>
            </div>

        </x-admin.form>

        {{-- ========== FOOTER ========== --}}
        <div x-show="tab === 'footer'" x-cloak>
            <x-admin.panel title="Footer columns">
                <div x-init="initSortable($el.querySelector('[data-sortable]'), @js(route('admin.theme.footer.reorder')))">
                    <div data-sortable>
                        @foreach ($footerColumns as $col)
                            <div class="v-dragrow" draggable="true" data-id="{{ $col->id }}">
                                <x-admin.icon name="menu" class="v-dragrow__grip drag-handle" />
                                <span class="v-dragrow__move">
                                    <button type="button" aria-label="Move {{ $col->title }} up" title="Move up"
                                            onclick="nudgeRow(this, -1, '{{ route('admin.theme.footer.reorder') }}')">&uarr;</button>
                                    <button type="button" aria-label="Move {{ $col->title }} down" title="Move down"
                                            onclick="nudgeRow(this, 1, '{{ route('admin.theme.footer.reorder') }}')">&darr;</button>
                                </span>
                                <span class="v-badge v-badge--quiet">{{ $col->type }}</span>
                                <span class="v-dir__name">{{ $col->title }}</span>
                                <span class="v-dragrow__end">
                                    <span class="v-badge {{ $col->is_enabled ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $col->is_enabled ? 'Active' : 'Disabled' }}</span>
                                    <a href="{{ route('admin.theme.footer.edit', $col) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-row__x" @click="$dispatch('v-confirm', 'delete-footer-col-{{ $col->id }}')" aria-label="Delete {{ $col->title }} footer column">
                                        <x-admin.icon name="trash" />
                                    </button>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="v-gap-lg">
                    <div class="v-label v-gap-sm">Add footer column</div>
                    <form method="POST" action="{{ route('admin.theme.footer.store') }}" class="v-addrow v-addrow--3">
                        @csrf
                        <x-admin.field name="type" label="Type" required>
                            <select name="type" class="v-control">
                                <option value="links">Links</option>
                                <option value="payment_icons">Payment icons</option>
                                <option value="social">Social links</option>
                                <option value="text">Free text</option>
                            </select>
                        </x-admin.field>
                        <x-admin.field name="title" label="Title" required>
                            <input type="text" name="title" required class="v-control">
                        </x-admin.field>
                        <div class="v-addrow__opts">
                            <button type="submit" class="v-btn v-btn--primary">Add column</button>
                        </div>
                    </form>
                </div>
            </x-admin.panel>

            @foreach ($footerColumns as $col)
                <x-admin.confirm name="delete-footer-col-{{ $col->id }}" title="Delete {{ $col->title }}?"
                    message="The column leaves the storefront footer immediately."
                    confirm="Delete" :action="route('admin.theme.footer.destroy', $col)" method="DELETE" />
            @endforeach
        </div>

        </div>
        </div>
    </div>

    @push('scripts')
    <script>
        /**
         * The section menu.
         *
         * It used to guard a tab switch with a modal, because each section was
         * its own save scope and moving on abandoned the edits. One form spans
         * every settings section now, so a switch loses nothing: the form is
         * still mounted, still dirty, still one Save away. The guard and its
         * three buttons went with the split that needed them.
         */
        function themeSections(initial) {
            return {
                tab: initial,

                pick(t) { this.go(t); },

                go(t) {
                    this.tab = t;
                    const u = new URL(window.location);
                    u.searchParams.set('tab', t);
                    history.replaceState(null, '', u);
                },
            };
        }

        /**
         * Move a row without dragging.
         *
         * HTML5 drag does not fire on touch and cannot be driven from a
         * keyboard, so the grip on these two lists was an invitation to an
         * impossible action on a phone. The Layout editor grew up and down
         * buttons; these lists did not, two clicks away. Same endpoint, same
         * payload as the drag.
         */
        function nudgeRow(button, by, reorderUrl) {
            const row = button.closest('[data-id]');
            const container = row.parentElement;
            const rows = [...container.querySelectorAll(':scope > [data-id]')];
            const i = rows.indexOf(row);
            const to = i + by;

            if (to < 0 || to >= rows.length) return;

            by < 0 ? container.insertBefore(row, rows[to]) : container.insertBefore(rows[to], row);

            const order = [...container.querySelectorAll(':scope > [data-id]')]
                .map((el, idx) => ({ id: parseInt(el.dataset.id), sort_order: idx }));

            fetch(reorderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify({ order }),
            }).then(() => window.vToast?.('Order saved.'));

            button.focus();
        }

        function initSortable(container, reorderUrl) {
            if (!container) return;
            let dragEl = null;

            container.addEventListener('dragstart', function (e) {
                const item = e.target.closest('[data-id]');
                if (!item) return;
                dragEl = item;
                item.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', item.dataset.id);
            });

            container.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (!dragEl) return;
                const target = e.target.closest('[data-id]');
                if (!target || target === dragEl) return;
                const rect = target.getBoundingClientRect();
                if (e.clientY < rect.top + rect.height / 2) target.before(dragEl);
                else target.after(dragEl);
            });

            container.addEventListener('drop', function (e) {
                e.preventDefault();
                if (!dragEl) return;
                const order = [...container.querySelectorAll(':scope > [data-id]')]
                    .map((el, idx) => ({ id: parseInt(el.dataset.id), sort_order: idx }));
                fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ order }),
                }).then(r => {
                    window.Alpine?.store('toasts')?.push(r.ok ? 'Order saved.' : 'Could not save the new order.', r.ok ? 'good' : 'bad');
                });
            });

            container.addEventListener('dragend', function () {
                if (dragEl) dragEl.classList.remove('is-dragging');
                dragEl = null;
            });
        }
    </script>
    @endpush
</x-admin.shell>
