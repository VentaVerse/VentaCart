    {{-- Mobile Search --}}
    <div class="md:hidden bg-[var(--color-card-bg)] border-b border-[var(--color-border)] px-4 py-2">
        <div x-data="searchAutocomplete()" @click.away="close()">
            <form action="{{ route('search.results') }}" method="GET" class="relative">
                <input type="text" name="q" placeholder="Search products..." value="{{ request('q') }}"
                    x-model="query" @input.debounce.300ms="search()" @focus="if (results.length) showResults = true" @keydown.escape="close()"
                    autocomplete="off"
                    class="w-full bg-[var(--color-input-bg)] border border-[var(--color-border)] rounded-lg text-sm text-[var(--color-text)] placeholder-[var(--color-text-muted)] px-4 py-2.5 pr-10 focus:outline-none focus:border-[var(--color-primary)]">
                <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>

                {{-- Mobile Autocomplete Dropdown --}}
                <div class="search-dropdown" x-show="showResults" x-cloak x-transition.opacity>
                    <template x-if="loading">
                        <div class="search-dropdown-loading">
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
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm text-[var(--color-text)] truncate" x-text="item.name"></div>
                                        <div class="text-sm font-bold" style="color: var(--color-primary);" x-text="money(item.price)"></div>
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
    </div>
