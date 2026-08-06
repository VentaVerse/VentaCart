<x-admin.shell title="SEO Redirects">
    <x-admin.page title="Redirects"
        :breadcrumb="[['label' => 'SEO', 'url' => route('admin.extensions.seo.dashboard')], ['label' => 'Redirects']]">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.extensions.seo.partials.nav-v2')

            <div class="min-w-0 flex-1 space-y-5">
                @if ($errors->any())
                    <div class="rounded-v-lg border border-v-border bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] px-4 py-3 text-[13px] text-v-danger">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {{-- Left: existing redirects list --}}
                    <div class="lg:col-span-2">
                        <x-admin.card title="Redirects ({{ $redirects->total() }})">
                            <x-slot name="headerActions">
                                <form method="GET" class="flex items-center gap-2">
                                    <x-admin.input type="text" name="q" :value="$search" placeholder="Search source / destination / note" class="w-64" />
                                    @if ($search !== '')
                                        <a href="{{ route('admin.extensions.seo.redirects.index') }}" class="text-xs text-v-muted hover:text-v-text">Clear</a>
                                    @endif
                                </form>
                            </x-slot>

                            <x-admin.table>
                                <x-slot name="head">
                                    <x-admin.table.th>Source → Destination</x-admin.table.th>
                                    <x-admin.table.th>Code</x-admin.table.th>
                                    <x-admin.table.th align="right">Hits</x-admin.table.th>
                                    <x-admin.table.th>Last hit</x-admin.table.th>
                                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                                </x-slot>
                                    @forelse ($redirects as $r)
                                        <x-admin.table.row x-data="{ editing: false }">
                                            <x-admin.table.cell>
                                                <div x-show="!editing">
                                                    <div class="max-w-md truncate font-mono text-xs text-v-text">{{ $r->source_path }}</div>
                                                    <div class="max-w-md truncate font-mono text-xs text-v-muted">
                                                        @if ((int) $r->status_code === 410)
                                                            <span class="italic text-v-warning">410 Gone</span>
                                                        @else
                                                            → {{ $r->destination_path }}
                                                            @if ($r->preserve_query)
                                                                <span class="ml-1 text-[10px] uppercase text-v-accent">+query</span>
                                                            @endif
                                                        @endif
                                                    </div>
                                                    @if ($r->note)
                                                        <div class="mt-0.5 text-xs text-v-muted">{{ $r->note }}</div>
                                                    @endif
                                                    @if (! $r->is_active)
                                                        <span class="mt-1 inline-block rounded bg-v-sunken px-1.5 py-0.5 text-[10px] uppercase text-v-secondary">disabled</span>
                                                    @endif
                                                </div>
                                                <form method="POST" action="{{ route('admin.extensions.seo.redirects.update', $r) }}" x-show="editing" x-cloak class="space-y-2 py-2">
                                                    @csrf @method('PUT')
                                                    <input type="text" name="source_path" value="{{ $r->source_path }}" required
                                                        class="v-control h-[var(--control-h)] w-full rounded-v-md border border-v-border bg-v-surface px-2.5 font-mono text-xs text-v-text">
                                                    <input type="text" name="destination_path" value="{{ $r->destination_path }}"
                                                        placeholder="/destination/path"
                                                        class="v-control h-[var(--control-h)] w-full rounded-v-md border border-v-border bg-v-surface px-2.5 font-mono text-xs text-v-text">
                                                    <div class="flex items-center gap-2 text-xs">
                                                        <select name="status_code" class="v-control h-[var(--control-h)] rounded-v-md border border-v-border bg-v-surface px-2 text-xs text-v-text">
                                                            <option value="301" @selected($r->status_code == 301)>301</option>
                                                            <option value="302" @selected($r->status_code == 302)>302</option>
                                                            <option value="410" @selected($r->status_code == 410)>410</option>
                                                        </select>
                                                        <label class="flex items-center gap-1 text-v-secondary">
                                                            <input type="checkbox" name="preserve_query" value="1" @checked($r->preserve_query) class="rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                                            preserve query
                                                        </label>
                                                        <label class="flex items-center gap-1 text-v-secondary">
                                                            <input type="checkbox" name="is_active" value="1" @checked($r->is_active) class="rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                                            active
                                                        </label>
                                                    </div>
                                                    <input type="text" name="note" value="{{ $r->note }}" placeholder="Note (optional)"
                                                        class="v-control h-[var(--control-h)] w-full rounded-v-md border border-v-border bg-v-surface px-2.5 text-xs text-v-text">
                                                    <div class="flex items-center gap-2">
                                                        <x-admin.button type="submit" variant="primary" size="sm">Save</x-admin.button>
                                                        <x-admin.button type="button" variant="ghost" size="sm" @click="editing = false">Cancel</x-admin.button>
                                                    </div>
                                                </form>
                                            </x-admin.table.cell>
                                            <x-admin.table.cell>
                                                <span class="rounded bg-v-sunken px-2 py-0.5 font-mono text-xs text-v-secondary">{{ $r->status_code }}</span>
                                            </x-admin.table.cell>
                                            <x-admin.table.cell align="right" mono>{{ number_format($r->hits) }}</x-admin.table.cell>
                                            <x-admin.table.cell class="text-xs text-v-muted">{{ $r->last_hit_at?->diffForHumans() ?? '—' }}</x-admin.table.cell>
                                            <x-admin.table.cell align="right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button type="button" @click="editing = !editing" class="text-xs text-v-accent hover:opacity-80">Edit</button>
                                                    <form method="POST" action="{{ route('admin.extensions.seo.redirects.destroy', $r) }}" onsubmit="return confirm('Delete this redirect?')" class="inline">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="text-xs text-v-danger hover:opacity-80">Delete</button>
                                                    </form>
                                                </div>
                                            </x-admin.table.cell>
                                        </x-admin.table.row>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-12 text-center">
                                                <x-admin.empty-state icon="external" title="No redirects yet" description="Add your first one on the right." />
                                            </td>
                                        </tr>
                                    @endforelse
                            </x-admin.table>

                            @if ($redirects->hasPages())
                                <div class="pt-3">
                                    {{ $redirects->links() }}
                                </div>
                            @endif
                        </x-admin.card>
                    </div>

                    {{-- Right: add new --}}
                    <div class="lg:col-span-1">
                        <div class="sticky top-11">
                            <x-admin.card title="Add Redirect">
                                <form method="POST" action="{{ route('admin.extensions.seo.redirects.store') }}" class="space-y-3">
                                    @csrf
                                    <x-admin.field name="source_path" label="Source path" help="Must start with /. Exact path match. Query strings on the request are ignored for the lookup.">
                                        <x-admin.input type="text" name="source_path" :value="old('source_path', $prefill['source_path'] ?? '')"
                                            placeholder="/old-product-slug" required class="font-mono" />
                                    </x-admin.field>
                                    <x-admin.field name="destination_path" label="Destination" help="Leave blank only when status code is 410 (Gone).">
                                        <x-admin.input type="text" name="destination_path" :value="old('destination_path', $prefill['destination_path'] ?? '')"
                                            placeholder="/product/new-slug or full URL" class="font-mono" />
                                    </x-admin.field>
                                    <div class="grid grid-cols-2 gap-2">
                                        <x-admin.field name="status_code" label="Status code">
                                            <x-admin.select name="status_code">
                                                <option value="301">301 Permanent</option>
                                                <option value="302">302 Temporary</option>
                                                <option value="410">410 Gone</option>
                                            </x-admin.select>
                                        </x-admin.field>
                                        <div class="flex items-end gap-3 pb-1 text-xs">
                                            <label class="flex items-center gap-1 text-v-secondary">
                                                <input type="checkbox" name="preserve_query" value="1" class="rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                                preserve query
                                            </label>
                                            <label class="flex items-center gap-1 text-v-secondary">
                                                <input type="checkbox" name="is_active" value="1" checked class="rounded-v-sm border-v-strong bg-v-surface text-v-accent focus:ring-v-accent">
                                                active
                                            </label>
                                        </div>
                                    </div>
                                    <x-admin.field name="note" label="Note (optional)">
                                        <x-admin.input type="text" name="note" :value="old('note')" placeholder="Why this exists" maxlength="255" />
                                    </x-admin.field>
                                    <x-admin.button type="submit" variant="primary" class="w-full">Add Redirect</x-admin.button>
                                </form>

                                <x-slot name="footer">
                                    <div class="space-y-1 text-[11px] text-v-secondary">
                                        <p><strong class="text-v-text">301 Permanent</strong> — preserves SEO link equity to the new URL. Use for moved content.</p>
                                        <p><strong class="text-v-text">302 Temporary</strong> — Google may keep indexing the old URL. Use for short-term moves.</p>
                                        <p><strong class="text-v-text">410 Gone</strong> — tells search engines the URL is permanently removed. Drops from index faster than 404.</p>
                                    </div>
                                </x-slot>
                            </x-admin.card>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
