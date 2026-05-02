<x-admin-layout>
    <x-slot name="header">SEO Optimization</x-slot>

    @include('admin.extensions.seo.partials.tabs')

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: existing redirects list --}}
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Redirects ({{ $redirects->total() }})</h3>
                <form method="GET" class="flex items-center gap-2">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search source / destination / note"
                        class="rounded-md border-gray-300 text-sm w-64">
                    @if ($search !== '')
                        <a href="{{ route('admin.extensions.seo.redirects.index') }}" class="text-xs text-gray-500 hover:text-gray-700">Clear</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-5 py-3 font-medium">Source → Destination</th>
                            <th class="px-5 py-3 font-medium">Code</th>
                            <th class="px-5 py-3 font-medium text-right">Hits</th>
                            <th class="px-5 py-3 font-medium">Last hit</th>
                            <th class="px-5 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($redirects as $r)
                            <tr class="hover:bg-gray-50" x-data="{ editing: false }">
                                <td class="px-5 py-3">
                                    <div x-show="!editing">
                                        <div class="font-mono text-xs text-gray-900 truncate max-w-md">{{ $r->source_path }}</div>
                                        <div class="font-mono text-xs text-gray-500 truncate max-w-md">
                                            @if ((int) $r->status_code === 410)
                                                <span class="italic text-amber-700">410 Gone</span>
                                            @else
                                                → {{ $r->destination_path }}
                                                @if ($r->preserve_query)
                                                    <span class="ml-1 text-[10px] text-indigo-600 uppercase">+query</span>
                                                @endif
                                            @endif
                                        </div>
                                        @if ($r->note)
                                            <div class="text-xs text-gray-400 mt-0.5">{{ $r->note }}</div>
                                        @endif
                                        @if (! $r->is_active)
                                            <span class="inline-block mt-1 rounded bg-gray-200 text-gray-600 text-[10px] uppercase px-1.5 py-0.5">disabled</span>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('admin.extensions.seo.redirects.update', $r) }}" x-show="editing" x-cloak class="space-y-2 py-2">
                                        @csrf @method('PUT')
                                        <input type="text" name="source_path" value="{{ $r->source_path }}" required
                                            class="w-full rounded-md border-gray-300 text-xs font-mono">
                                        <input type="text" name="destination_path" value="{{ $r->destination_path }}"
                                            placeholder="/destination/path"
                                            class="w-full rounded-md border-gray-300 text-xs font-mono">
                                        <div class="flex items-center gap-2 text-xs">
                                            <select name="status_code" class="rounded-md border-gray-300 text-xs">
                                                <option value="301" @selected($r->status_code == 301)>301</option>
                                                <option value="302" @selected($r->status_code == 302)>302</option>
                                                <option value="410" @selected($r->status_code == 410)>410</option>
                                            </select>
                                            <label class="flex items-center gap-1">
                                                <input type="checkbox" name="preserve_query" value="1" @checked($r->preserve_query)>
                                                preserve query
                                            </label>
                                            <label class="flex items-center gap-1">
                                                <input type="checkbox" name="is_active" value="1" @checked($r->is_active)>
                                                active
                                            </label>
                                        </div>
                                        <input type="text" name="note" value="{{ $r->note }}" placeholder="Note (optional)"
                                            class="w-full rounded-md border-gray-300 text-xs">
                                        <div class="flex items-center gap-2">
                                            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700">Save</button>
                                            <button type="button" @click="editing = false" class="text-xs text-gray-500 hover:text-gray-700">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                                <td class="px-5 py-3 text-xs">
                                    <span class="rounded bg-gray-100 px-2 py-0.5 font-mono">{{ $r->status_code }}</span>
                                </td>
                                <td class="px-5 py-3 text-right text-gray-700 tabular-nums">{{ number_format($r->hits) }}</td>
                                <td class="px-5 py-3 text-xs text-gray-500">
                                    {{ $r->last_hit_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" @click="editing = !editing" class="text-xs text-indigo-600 hover:text-indigo-800">Edit</button>
                                        <form method="POST" action="{{ route('admin.extensions.seo.redirects.destroy', $r) }}" onsubmit="return confirm('Delete this redirect?')" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:text-red-800">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500">
                                    No redirects yet. Add your first one on the right.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($redirects->hasPages())
                <div class="px-5 py-3 border-t border-gray-200">
                    {{ $redirects->links() }}
                </div>
            @endif
        </div>

        {{-- Right: add new --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 sticky top-4">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Add Redirect</h3>
                </div>
                <form method="POST" action="{{ route('admin.extensions.seo.redirects.store') }}" class="px-5 py-4 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Source path</label>
                        <input type="text" name="source_path" value="{{ old('source_path', $prefill['source_path'] ?? '') }}"
                            placeholder="/old-product-slug" required
                            class="w-full rounded-md border-gray-300 text-sm font-mono">
                        <p class="text-[11px] text-gray-500 mt-1">Must start with /. Exact path match. Query strings on the request are ignored for the lookup.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Destination</label>
                        <input type="text" name="destination_path" value="{{ old('destination_path', $prefill['destination_path'] ?? '') }}"
                            placeholder="/product/new-slug or full URL"
                            class="w-full rounded-md border-gray-300 text-sm font-mono">
                        <p class="text-[11px] text-gray-500 mt-1">Leave blank only when status code is 410 (Gone).</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Status code</label>
                            <select name="status_code" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="301">301 Permanent</option>
                                <option value="302">302 Temporary</option>
                                <option value="410">410 Gone</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-3 text-xs pb-1">
                            <label class="flex items-center gap-1">
                                <input type="checkbox" name="preserve_query" value="1">
                                preserve query
                            </label>
                            <label class="flex items-center gap-1">
                                <input type="checkbox" name="is_active" value="1" checked>
                                active
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Note <span class="text-gray-400">(optional)</span></label>
                        <input type="text" name="note" value="{{ old('note') }}"
                            placeholder="Why this exists" maxlength="255"
                            class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Add Redirect
                    </button>
                </form>
                <div class="px-5 py-3 border-t border-gray-200 bg-gray-50 text-[11px] text-gray-600 space-y-1">
                    <p><strong>301 Permanent</strong> — preserves SEO link equity to the new URL. Use for moved content.</p>
                    <p><strong>302 Temporary</strong> — Google may keep indexing the old URL. Use for short-term moves.</p>
                    <p><strong>410 Gone</strong> — tells search engines the URL is permanently removed. Drops from index faster than 404.</p>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
