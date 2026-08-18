<x-admin.seo-shell title="Redirects" active="redirects">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Redirects</h1>
        </div>
    </div>

    <div class="v-settle">
        @if ($errors->any())
            <div class="v-notice">
                <p class="v-notice__title">That redirect was not saved</p>
                <p class="v-notice__text">{{ implode(' ', $errors->all()) }}</p>
            </div>
        @endif

        <div class="v-split">
            <div>
                <x-admin.panel :title="'Redirects (' . $redirects->total() . ')'" flush>
                    <x-slot:acts>
                        <form method="GET" class="v-namerow">
                            <input type="search" name="q" value="{{ $search }}" placeholder="Search source, destination or note" aria-label="Search redirects" class="v-control v-w-search">
                            @if ($search !== '')
                                <a href="{{ route('admin.extensions.seo.redirects.index') }}" class="v-btn v-btn--ghost v-btn--sm">Clear</a>
                            @endif
                        </form>
                    </x-slot:acts>

                    <div class="v-tablewrap">
                        <table class="v-table">
                            <thead>
                                <tr>
                                    <th>Source and destination</th><th>Code</th><th>Hits</th><th>Last hit</th>
                                    <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($redirects as $r)
                                    <tr x-data="{ editing: false }" class="{{ $r->is_active ? '' : 'v-row-dim' }}">
                                        <td>
                                            <div x-show="!editing">
                                                <span class="v-mono v-block v-cell-url" title="{{ $r->source_path }}">{{ $r->source_path }}</span>
                                                <span class="v-mono v-cell-quiet v-block v-cell-url">
                                                    @if ((int) $r->status_code === 410)
                                                        410 Gone
                                                    @else
                                                        &rarr; {{ $r->destination_path }}@if ($r->preserve_query) +query @endif
                                                    @endif
                                                </span>
                                                @if ($r->note)<span class="v-cell-quiet v-block">{{ $r->note }}</span>@endif
                                                @unless ($r->is_active)<span class="v-badge v-badge--quiet">Disabled</span>@endunless
                                            </div>
                                            <form method="POST" action="{{ route('admin.extensions.seo.redirects.update', $r) }}" x-show="editing" x-cloak class="v-rowedit">
                                                @csrf @method('PUT')
                                                <div class="v-rowedit__line">
                                                    <input type="text" name="source_path" value="{{ $r->source_path }}" required aria-label="Source path" class="v-control v-mono">
                                                </div>
                                                <div class="v-rowedit__line">
                                                    <input type="text" name="destination_path" value="{{ $r->destination_path }}" placeholder="/destination/path" aria-label="Destination path" class="v-control v-mono">
                                                </div>
                                                <div class="v-rowedit__line">
                                                    <select name="status_code" aria-label="Status code" class="v-control v-w-name">
                                                        <option value="301" @selected($r->status_code == 301)>301</option>
                                                        <option value="302" @selected($r->status_code == 302)>302</option>
                                                        <option value="410" @selected($r->status_code == 410)>410</option>
                                                    </select>
                                                    <label class="v-toggle">
                                                        <input type="hidden" name="preserve_query" value="0">
                                                        <input type="checkbox" name="preserve_query" value="1" @checked($r->preserve_query)>
                                                        <span class="v-toggle__track" aria-hidden="true"></span>
                                                        Preserve query
                                                    </label>
                                                    <label class="v-toggle">
                                                        <input type="hidden" name="is_active" value="0">
                                                        <input type="checkbox" name="is_active" value="1" @checked($r->is_active)>
                                                        <span class="v-toggle__track" aria-hidden="true"></span>
                                                        Active
                                                    </label>
                                                </div>
                                                <div class="v-rowedit__line">
                                                    <input type="text" name="note" value="{{ $r->note }}" placeholder="Note (optional)" aria-label="Note" class="v-control">
                                                    <button type="submit" class="v-btn v-btn--primary v-btn--sm">Save</button>
                                                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="editing = false">Cancel</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="v-mono">{{ $r->status_code }}</td>
                                        <td class="v-mono">{{ number_format($r->hits) }}</td>
                                        <td class="v-cell-quiet">{{ $r->last_hit_at?->diffForHumans() ?? '-' }}</td>
                                        <td class="v-cell-act">
                                            <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="editing = !editing">Edit</button>
                                            <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-redirect-{{ $r->id }}')">Delete</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <div class="v-empty">
                                                <p class="v-empty__title">No redirects yet</p>
                                                <p class="v-empty__line">Add the first one beside this list.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($redirects->hasPages())
                        <x-admin.pager :paginator="$redirects" />
                    @endif
                </x-admin.panel>

                @foreach ($redirects as $r)
                    <x-admin.confirm name="delete-redirect-{{ $r->id }}" title="Delete this redirect?"
                        message="{{ $r->source_path }} stops redirecting and starts returning a 404 again. Its {{ number_format($r->hits) }} recorded hits are deleted with it."
                        confirm="Delete" :action="route('admin.extensions.seo.redirects.destroy', $r)" method="DELETE" />
                @endforeach
            </div>

            <x-admin.panel title="Add redirect"
                note="301 passes link equity and is right for moved content. 302 keeps the old URL indexed, for a short-term move. 410 drops the URL from the index faster than a 404.">
                <form method="POST" action="{{ route('admin.extensions.seo.redirects.store') }}">
                    @csrf
                    <x-admin.field name="source_path" label="Source path" required
                        hint="Must start with /. Exact path match; query strings on the request are ignored for the lookup.">
                        <input type="text" name="source_path" value="{{ old('source_path', $prefill['source_path'] ?? '') }}" required class="v-control v-mono">
                    </x-admin.field>
                    <div class="v-gap">
                        <x-admin.field name="destination_path" label="Destination" hint="Leave blank only when the status code is 410.">
                            <input type="text" name="destination_path" value="{{ old('destination_path', $prefill['destination_path'] ?? '') }}" class="v-control v-mono">
                        </x-admin.field>
                    </div>
                    <div class="v-gap">
                        <x-admin.field name="status_code" label="Status code" required>
                            <select name="status_code" class="v-control">
                                <option value="301">301 Permanent</option>
                                <option value="302">302 Temporary</option>
                                <option value="410">410 Gone</option>
                            </select>
                        </x-admin.field>
                    </div>
                    <div class="v-gap v-actrow">
                        <label class="v-toggle">
                            <input type="hidden" name="preserve_query" value="0">
                            <input type="checkbox" name="preserve_query" value="1">
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            Preserve query
                        </label>
                        <label class="v-toggle">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            Active
                        </label>
                    </div>
                    <div class="v-gap">
                        <x-admin.field name="note" label="Note (optional)">
                            <input type="text" name="note" value="{{ old('note') }}" maxlength="255" class="v-control">
                        </x-admin.field>
                    </div>
                    <div class="v-gap">
                        <button type="submit" class="v-btn v-btn--primary">Add redirect</button>
                    </div>
                </form>
            </x-admin.panel>
        </div>
    </div>
</x-admin.seo-shell>
