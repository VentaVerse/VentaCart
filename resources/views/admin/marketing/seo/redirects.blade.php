<x-admin3.seo-shell title="Redirects" active="redirects">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Redirects</h1>
        </div>
    </div>

    <div class="v3-settle">
        @if ($errors->any())
            <div class="v3-notice">
                <p class="v3-notice__title">That redirect was not saved</p>
                <p class="v3-notice__text">{{ implode(' ', $errors->all()) }}</p>
            </div>
        @endif

        <div class="v3-split">
            <div>
                <x-admin3.panel :title="'Redirects (' . $redirects->total() . ')'" flush>
                    <x-slot:acts>
                        <form method="GET" class="v3-namerow">
                            <input type="search" name="q" value="{{ $search }}" placeholder="Search source, destination or note" aria-label="Search redirects" class="v3-control v3-w-search">
                            @if ($search !== '')
                                <a href="{{ route('admin.extensions.seo.redirects.index') }}" class="v3-btn v3-btn--ghost v3-btn--sm">Clear</a>
                            @endif
                        </form>
                    </x-slot:acts>

                    <div class="v3-tablewrap">
                        <table class="v3-table">
                            <thead>
                                <tr>
                                    <th>Source and destination</th><th>Code</th><th>Hits</th><th>Last hit</th>
                                    <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($redirects as $r)
                                    <tr x-data="{ editing: false }" class="{{ $r->is_active ? '' : 'v3-row-dim' }}">
                                        <td>
                                            <div x-show="!editing">
                                                <span class="v3-mono v3-block v3-cell-url" title="{{ $r->source_path }}">{{ $r->source_path }}</span>
                                                <span class="v3-mono v3-cell-quiet v3-block v3-cell-url">
                                                    @if ((int) $r->status_code === 410)
                                                        410 Gone
                                                    @else
                                                        &rarr; {{ $r->destination_path }}@if ($r->preserve_query) +query @endif
                                                    @endif
                                                </span>
                                                @if ($r->note)<span class="v3-cell-quiet v3-block">{{ $r->note }}</span>@endif
                                                @unless ($r->is_active)<span class="v3-badge v3-badge--quiet">Disabled</span>@endunless
                                            </div>
                                            <form method="POST" action="{{ route('admin.extensions.seo.redirects.update', $r) }}" x-show="editing" x-cloak class="v3-rowedit">
                                                @csrf @method('PUT')
                                                <div class="v3-rowedit__line">
                                                    <input type="text" name="source_path" value="{{ $r->source_path }}" required aria-label="Source path" class="v3-control v3-mono">
                                                </div>
                                                <div class="v3-rowedit__line">
                                                    <input type="text" name="destination_path" value="{{ $r->destination_path }}" placeholder="/destination/path" aria-label="Destination path" class="v3-control v3-mono">
                                                </div>
                                                <div class="v3-rowedit__line">
                                                    <select name="status_code" aria-label="Status code" class="v3-control v3-w-name">
                                                        <option value="301" @selected($r->status_code == 301)>301</option>
                                                        <option value="302" @selected($r->status_code == 302)>302</option>
                                                        <option value="410" @selected($r->status_code == 410)>410</option>
                                                    </select>
                                                    <label class="v3-toggle">
                                                        <input type="hidden" name="preserve_query" value="0">
                                                        <input type="checkbox" name="preserve_query" value="1" @checked($r->preserve_query)>
                                                        <span class="v3-toggle__track" aria-hidden="true"></span>
                                                        Preserve query
                                                    </label>
                                                    <label class="v3-toggle">
                                                        <input type="hidden" name="is_active" value="0">
                                                        <input type="checkbox" name="is_active" value="1" @checked($r->is_active)>
                                                        <span class="v3-toggle__track" aria-hidden="true"></span>
                                                        Active
                                                    </label>
                                                </div>
                                                <div class="v3-rowedit__line">
                                                    <input type="text" name="note" value="{{ $r->note }}" placeholder="Note (optional)" aria-label="Note" class="v3-control">
                                                    <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">Save</button>
                                                    <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="editing = false">Cancel</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="v3-mono">{{ $r->status_code }}</td>
                                        <td class="v3-mono">{{ number_format($r->hits) }}</td>
                                        <td class="v3-cell-quiet">{{ $r->last_hit_at?->diffForHumans() ?? '-' }}</td>
                                        <td class="v3-cell-act">
                                            <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="editing = !editing">Edit</button>
                                            <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-redirect-{{ $r->id }}')">Delete</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <div class="v3-empty">
                                                <p class="v3-empty__title">No redirects yet</p>
                                                <p class="v3-empty__line">Add the first one beside this list.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($redirects->hasPages())
                        <div class="v3-pager">{{ $redirects->links() }}</div>
                    @endif
                </x-admin3.panel>

                @foreach ($redirects as $r)
                    <x-admin3.confirm name="delete-redirect-{{ $r->id }}" title="Delete this redirect?"
                        message="{{ $r->source_path }} stops redirecting and starts returning a 404 again. Its {{ number_format($r->hits) }} recorded hits are deleted with it."
                        confirm="Delete" :action="route('admin.extensions.seo.redirects.destroy', $r)" method="DELETE" />
                @endforeach
            </div>

            <x-admin3.panel title="Add redirect"
                note="301 passes link equity and is right for moved content. 302 keeps the old URL indexed, for a short-term move. 410 drops the URL from the index faster than a 404.">
                <form method="POST" action="{{ route('admin.extensions.seo.redirects.store') }}">
                    @csrf
                    <x-admin3.field name="source_path" label="Source path" required
                        hint="Must start with /. Exact path match; query strings on the request are ignored for the lookup.">
                        <input type="text" name="source_path" value="{{ old('source_path', $prefill['source_path'] ?? '') }}" required class="v3-control v3-mono">
                    </x-admin3.field>
                    <div class="v3-gap">
                        <x-admin3.field name="destination_path" label="Destination" hint="Leave blank only when the status code is 410.">
                            <input type="text" name="destination_path" value="{{ old('destination_path', $prefill['destination_path'] ?? '') }}" class="v3-control v3-mono">
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap">
                        <x-admin3.field name="status_code" label="Status code" required>
                            <select name="status_code" class="v3-control">
                                <option value="301">301 Permanent</option>
                                <option value="302">302 Temporary</option>
                                <option value="410">410 Gone</option>
                            </select>
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap v3-actrow">
                        <label class="v3-toggle">
                            <input type="hidden" name="preserve_query" value="0">
                            <input type="checkbox" name="preserve_query" value="1">
                            <span class="v3-toggle__track" aria-hidden="true"></span>
                            Preserve query
                        </label>
                        <label class="v3-toggle">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span class="v3-toggle__track" aria-hidden="true"></span>
                            Active
                        </label>
                    </div>
                    <div class="v3-gap">
                        <x-admin3.field name="note" label="Note (optional)">
                            <input type="text" name="note" value="{{ old('note') }}" maxlength="255" class="v3-control">
                        </x-admin3.field>
                    </div>
                    <div class="v3-gap">
                        <button type="submit" class="v3-btn v3-btn--primary">Add redirect</button>
                    </div>
                </form>
            </x-admin3.panel>
        </div>
    </div>
</x-admin3.seo-shell>
