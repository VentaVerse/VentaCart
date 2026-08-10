<x-admin.seo-shell title="SEO Redirects" heading="Redirects">
    <div class="space-y-5">
        @if ($errors->any())
            <x-admin.empty-state variant="notice" title="That redirect was not saved"
                :description="implode(' ', $errors->all())" />
        @endif

        <div class="v-split">
            <div class="v-split__main">
                <x-admin.panel title="Redirects" :note="$redirects->total() . ' in place'" flush>
                    <x-slot:head>
                        <form method="GET" class="ml-auto flex items-center gap-2">
                            <x-admin.input type="search" name="q" :value="$search" placeholder="Search source, destination or note" class="w-64" aria-label="Search redirects" />
                            @if ($search !== '')
                                <a href="{{ route('admin.extensions.seo.redirects.index') }}" class="v-filters__reset">Clear</a>
                            @endif
                        </form>
                    </x-slot:head>

                    <x-admin.table bare>
                        <x-slot:head>
                            <x-admin.table.th>Source <x-admin.icon name="arrow-right" class="inline w-3 h-3 align-[-1px]" /> Destination</x-admin.table.th>
                            <x-admin.table.th>Code</x-admin.table.th>
                            <x-admin.table.th align="right">Hits</x-admin.table.th>
                            <x-admin.table.th>Last hit</x-admin.table.th>
                            <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                        </x-slot:head>
                        @forelse ($redirects as $r)
                            <x-admin.table.row x-data="{ editing: false }">
                                <x-admin.table.cell>
                                    <div x-show="!editing">
                                        <div class="v-num max-w-md truncate text-xs text-v-text">{{ $r->source_path }}</div>
                                        <div class="v-num max-w-md truncate text-xs text-v-muted">
                                            @if ((int) $r->status_code === 410)
                                                <span class="italic text-v-warning">410 Gone</span>
                                            @else
                                                <x-admin.icon name="arrow-right" class="inline w-3 h-3 align-[-1px]" /> {{ $r->destination_path }}
                                                @if ($r->preserve_query)
                                                    <span class="ml-1 v-label text-v-accent">+query</span>
                                                @endif
                                            @endif
                                        </div>
                                        @if ($r->note)
                                            <div class="mt-0.5 text-xs text-v-muted">{{ $r->note }}</div>
                                        @endif
                                        @if (! $r->is_active)
                                            <span class="mt-1 inline-block"><x-admin.badge tone="neutral">Disabled</x-admin.badge></span>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('admin.extensions.seo.redirects.update', $r) }}" x-show="editing" x-cloak class="space-y-2 py-2">
                                        @csrf @method('PUT')
                                        <input type="text" name="source_path" value="{{ $r->source_path }}" required aria-label="Source path"
                                            class="v-control h-[var(--control-h-sm)] w-full rounded-v-md border bg-v-surface px-2.5 font-mono text-xs text-v-text">
                                        <input type="text" name="destination_path" value="{{ $r->destination_path }}"
                                            placeholder="/destination/path" aria-label="Destination path"
                                            class="v-control h-[var(--control-h-sm)] w-full rounded-v-md border bg-v-surface px-2.5 font-mono text-xs text-v-text">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <select name="status_code" aria-label="Status code"
                                                class="v-control h-[var(--control-h-sm)] rounded-v-md border bg-v-surface px-2 text-xs text-v-text">
                                                <option value="301" @selected($r->status_code == 301)>301</option>
                                                <option value="302" @selected($r->status_code == 302)>302</option>
                                                <option value="410" @selected($r->status_code == 410)>410</option>
                                            </select>
                                            <x-admin.checkbox name="preserve_query" label="Preserve query" :checked="$r->preserve_query" />
                                            <x-admin.checkbox name="is_active" label="Active" :checked="$r->is_active" />
                                        </div>
                                        <input type="text" name="note" value="{{ $r->note }}" placeholder="Note (optional)" aria-label="Note"
                                            class="v-control h-[var(--control-h-sm)] w-full rounded-v-md border bg-v-surface px-2.5 text-xs text-v-text">
                                        <div class="flex items-center gap-2">
                                            <x-admin.button type="submit" variant="primary" size="sm">Save</x-admin.button>
                                            <x-admin.button type="button" variant="ghost" size="sm" @click="editing = false">Cancel</x-admin.button>
                                        </div>
                                    </form>
                                </x-admin.table.cell>
                                <x-admin.table.cell mono class="text-v-secondary">{{ $r->status_code }}</x-admin.table.cell>
                                <x-admin.table.cell align="right" mono>{{ number_format($r->hits) }}</x-admin.table.cell>
                                <x-admin.table.cell class="text-xs text-v-muted">{{ $r->last_hit_at?->diffForHumans() ?? '-' }}</x-admin.table.cell>
                                <x-admin.table.cell class="v-col-act">
                                    <div class="v-rowactions v-rowactions--end">
                                        <x-admin.button type="button" size="sm" variant="ghost" @click="editing = !editing">Edit</x-admin.button>
                                        <x-admin.button type="button" size="sm" variant="ghost" class="v-rowactions__danger"
                                            @click="$dispatch('open-modal', 'delete-redirect-{{ $r->id }}')">Delete</x-admin.button>
                                    </div>
                                </x-admin.table.cell>
                            </x-admin.table.row>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-admin.empty-state title="No redirects yet" description="Add the first one beside this list." />
                                </td>
                            </tr>
                        @endforelse
                        @if ($redirects->hasPages())
                            <x-slot:footer>{{ $redirects->links() }}</x-slot:footer>
                        @endif
                    </x-admin.table>
                </x-admin.panel>

                @foreach ($redirects as $r)
                    <x-admin.confirm-dialog name="delete-redirect-{{ $r->id }}" title="Delete this redirect?"
                        message="{{ $r->source_path }} stops redirecting and starts returning a 404 again. Its {{ number_format($r->hits) }} recorded hits are deleted with it."
                        confirm="Delete" :action="route('admin.extensions.seo.redirects.destroy', $r)" method="DELETE" />
                @endforeach
            </div>

            <div class="v-split__rail">
                <x-admin.card title="Add redirect">
                    <x-slot name="headerActions">
                        <x-admin.hint label="Which status code to use" class="v-hint--end">301 passes link equity to the new URL and is right for moved content. 302 keeps the old URL indexed, for a short-term move. 410 drops the URL from the index faster than a 404.</x-admin.hint>
                    </x-slot>
                    <form method="POST" action="{{ route('admin.extensions.seo.redirects.store') }}" class="space-y-3">
                        @csrf
                        <x-admin.field name="source_path" label="Source path" help="Must start with /. Exact path match; query strings on the request are ignored for the lookup." required>
                            <x-admin.input type="text" name="source_path" :value="old('source_path', $prefill['source_path'] ?? '')"
                                placeholder="/old-product-slug" required class="font-mono" />
                        </x-admin.field>
                        <x-admin.field name="destination_path" label="Destination" help="Leave blank only when the status code is 410.">
                            <x-admin.input type="text" name="destination_path" :value="old('destination_path', $prefill['destination_path'] ?? '')"
                                placeholder="/product/new-slug or full URL" class="font-mono" />
                        </x-admin.field>
                        <x-admin.field name="status_code" label="Status code" required>
                            <x-admin.select name="status_code">
                                <option value="301">301 Permanent</option>
                                <option value="302">302 Temporary</option>
                                <option value="410">410 Gone</option>
                            </x-admin.select>
                        </x-admin.field>
                        <div class="flex flex-wrap items-center gap-4">
                            <x-admin.checkbox name="preserve_query" label="Preserve query" />
                            <x-admin.checkbox name="is_active" label="Active" :checked="true" />
                        </div>
                        <x-admin.field name="note" label="Note (optional)">
                            <x-admin.input type="text" name="note" :value="old('note')" placeholder="Why this exists" maxlength="255" />
                        </x-admin.field>
                        <x-admin.button type="submit" variant="primary" class="w-full">Add redirect</x-admin.button>
                    </form>
                </x-admin.card>
            </div>
        </div>
    </div>
</x-admin.seo-shell>
