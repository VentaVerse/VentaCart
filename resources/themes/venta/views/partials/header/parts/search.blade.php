            {{-- Search Bar with Autocomplete --}}
            <div class="{{ $searchClass ?? 'search-bar hidden md:flex' }}" x-data="searchAutocomplete()" @click.away="close()">
                <form action="{{ route('search.results') }}" method="GET" class="flex w-full relative">
                    <input
                        type="text"
                        name="q"
                        placeholder="Search pedals, cables, parts..."
                        value="{{ request('q') }}"
                        x-model="query"
                        @input.debounce.300ms="search()"
                        @focus="if (results.length) showResults = true"
                        @keydown.escape="close()"
                        autocomplete="off"
                    >
                    <button type="submit">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>

                    {{-- Autocomplete Dropdown --}}
                    <div class="search-dropdown" x-show="showResults" x-cloak x-transition.opacity>
                        <template x-if="loading">
                            <div class="search-dropdown-loading">
                                <div class="skeleton-bar"></div>
                                <div class="skeleton-bar"></div>
                                <div class="skeleton-bar"></div>
                            </div>
                        </template>
                        <template x-if="!loading && results.length > 0">
                            <div>
                                <template x-for="item in results" :key="item.slug">
                                    <a :href="item.url" class="search-result-item">
                                        <div class="search-result-img">
                                            <img :src="item.image || ''" :alt="item.name" x-show="item.image" loading="lazy">
                                            <div x-show="!item.image" class="w-full h-full flex items-center justify-center text-[var(--color-input-border)]">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm text-[var(--color-text)] truncate" x-text="item.name"></div>
                                            <div class="text-sm font-bold" style="font-family: var(--font-heading); color: var(--color-primary); letter-spacing: 0.05em;" x-text="money(item.price)"></div>
                                        </div>
                                    </a>
                                </template>
                            </div>
                        </template>
                        <template x-if="!loading && results.length === 0 && searched">
                            <div class="px-4 py-6 text-center text-sm text-[var(--color-text-muted)]">No products found</div>
                        </template>
                    </div>
                </form>
            </div>
