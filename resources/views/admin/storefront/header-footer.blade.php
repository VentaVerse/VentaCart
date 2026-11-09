<x-admin.shell title="Header & footer">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <span>Storefront</span>
                <span aria-hidden="true">/</span>
                <span>Header &amp; footer</span>
            </div>
            <h1 class="v-head__title">Header &amp; footer</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ url('/') }}" target="_blank" rel="noopener" class="v-btn v-btn--sm">View store</a>
        </div>
    </div>

    <nav class="v-tabs" aria-label="Header and footer">
        @foreach ($tabs as $vKey => $vLabel)
            <a class="v-tab" href="{{ route('admin.header-footer', ['tab' => $vKey]) }}" @if ($vKey === $tab) aria-current="page" @endif>{{ $vLabel }}</a>
        @endforeach
    </nav>

    @if ($tab === 'announcement')
        <x-admin.form :action="route('admin.header-footer.announcement')" class="v-settle">
            <x-admin.panel title="Announcement bar">
                <label class="v-toggle">
                    <input type="hidden" name="announcement_enabled" value="0">
                    <input type="checkbox" name="announcement_enabled" value="1"
                           @checked(old('announcement_enabled', $header['announcement_enabled'] ?? '0') === '1')>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Show the announcement bar
                </label>
                <div class="v-grid v-grid--2 v-gap">
                    <x-admin.field name="announcement_text" label="Text">
                        <input type="text" name="announcement_text" maxlength="255" class="v-control" value="{{ old('announcement_text', $header['announcement_text'] ?? '') }}">
                    </x-admin.field>
                    <x-admin.field name="announcement_link" label="Link" hint="A path such as /sale, or an http or https address. Empty for no link.">
                        <input type="text" name="announcement_link" maxlength="500" class="v-control v-mono" placeholder="/sale" value="{{ old('announcement_link', $header['announcement_link'] ?? '') }}">
                    </x-admin.field>
                </div>
            </x-admin.panel>
        </x-admin.form>

    @elseif ($tab === 'top-bar')
        <x-admin.form :action="route('admin.header-footer.top-bar')" class="v-settle">
            <x-admin.panel title="Top bar links">
                <div x-data="{ links: @js(json_decode($header['topbar_links'] ?? '[]', true) ?: []) }">
                    <template x-for="(link, idx) in links" :key="idx">
                        <div class="v-row">
                            <input type="text" x-model="link.label" aria-label="Link label" placeholder="Label" maxlength="100" class="v-control">
                            <input type="text" x-model="link.url" aria-label="Link address" placeholder="/delivery" maxlength="500" class="v-control v-mono">
                            <button type="button" class="v-row__x" @click="links.splice(idx, 1); $nextTick(() => touch())" aria-label="Remove link">
                                <x-admin.icon name="trash" />
                            </button>
                        </div>
                    </template>
                    <div class="v-gap">
                        <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="links.push({label: '', url: ''}); $nextTick(() => touch())">
                            <x-admin.icon name="plus" /> Add link
                        </button>
                    </div>
                    <input type="hidden" name="topbar_links" :value="JSON.stringify(links)">
                </div>
            </x-admin.panel>
        </x-admin.form>

    @elseif ($tab === 'main-menu')
        @include('admin.storefront.partials.main-menu')

    @else
        @php
            $vFooterRows = [];
            foreach ($footerColumns as $vCol) {
                $vFooterRows[] = ['id' => $vCol->id, 'title' => $vCol->title, 'type' => $vCol->type, 'on' => (bool) $vCol->is_enabled, 'edit' => route('admin.footer.edit', $vCol)];
            }
        @endphp
        {{-- The order is the form's: moved with the arrows, kept by Save. --}}
        <x-admin.form :action="route('admin.footer.arrange')" class="v-settle">
            <x-admin.panel title="Footer columns">
                <div x-data="{ cols: @js($vFooterRows) }">
                    <template x-for="(col, idx) in cols" :key="col.id">
                        <div class="v-dragrow">
                            <input type="hidden" name="order[]" :value="col.id">
                            <span class="v-dragrow__move">
                                <button type="button" :aria-label="'Move ' + col.title + ' up'" title="Move up" :disabled="idx === 0"
                                        @click="cols.splice(idx - 1, 0, cols.splice(idx, 1)[0]); $nextTick(() => touch())">&uarr;</button>
                                <button type="button" :aria-label="'Move ' + col.title + ' down'" title="Move down" :disabled="idx === cols.length - 1"
                                        @click="cols.splice(idx + 1, 0, cols.splice(idx, 1)[0]); $nextTick(() => touch())">&darr;</button>
                            </span>
                            <span class="v-badge v-badge--quiet" x-text="col.type"></span>
                            <span class="v-dir__name" x-text="col.title"></span>
                            <span class="v-dragrow__end">
                                <span class="v-badge" :class="col.on ? 'v-badge--good' : 'v-badge--quiet'" x-text="col.on ? 'Active' : 'Hidden'"></span>
                                <a :href="col.edit" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                <button type="button" class="v-row__x" @click="$dispatch('v-confirm', 'delete-footer-col-' + col.id)" :aria-label="'Delete ' + col.title">
                                    <x-admin.icon name="trash" />
                                </button>
                            </span>
                        </div>
                    </template>
                    <div class="v-empty" x-show="!cols.length" @if ($footerColumns->isNotEmpty()) x-cloak @endif>
                        <p class="v-empty__title">No footer columns yet</p>
                    </div>
                </div>
            </x-admin.panel>
        </x-admin.form>

        <x-admin.panel title="Add a column">
            <form method="POST" action="{{ route('admin.footer.store') }}" class="v-addrow v-addrow--3">
                @csrf
                <x-admin.field name="type" label="Kind" required>
                    <select name="type" class="v-control">
                        <option value="links">Links</option>
                        <option value="payment_icons">Payment icons</option>
                        <option value="social">Social links</option>
                        <option value="text">Free text</option>
                    </select>
                </x-admin.field>
                <x-admin.field name="title" label="Title" required>
                    <input type="text" name="title" required maxlength="255" class="v-control">
                </x-admin.field>
                <div class="v-addrow__opts">
                    <button type="submit" class="v-btn v-btn--secondary">Add column</button>
                </div>
            </form>
        </x-admin.panel>

        @foreach ($footerColumns as $vCol)
            <x-admin.confirm name="delete-footer-col-{{ $vCol->id }}" title="Delete {{ $vCol->title }}?"
                message="The column leaves the storefront footer."
                confirm="Delete" :action="route('admin.footer.destroy', $vCol)" method="DELETE" />
        @endforeach
    @endif
</x-admin.shell>
