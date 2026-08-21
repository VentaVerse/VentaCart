<x-admin.shell title="Media" active="catalog">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.products.index') }}">Catalog</a>
                <span aria-hidden="true">/</span>
                <span>Media</span>
            </div>
            <h1 class="v-head__title">Media</h1>
        </div>
    </div>

    {{-- This page used to be a weaker copy of the picker: the same browser
         with the choosing taken out, sitting one level above Products in the
         nav. It kept a nav slot for a read-only file explorer whose only
         exclusive control was Copy path.

         What it does now is the work no picker can do, because a picker is
         opened to answer one question and leaves: make a folder, rename one
         and carry every stored path with it, select many, and clear out what
         nothing is using. The browsing itself is the shared component, so
         this page and every picker cannot drift again. --}}
    <div class="v-settle" x-data="mediaPage()">
        <x-admin.media-picker name="__library" root="products" mode="multi"
                              can="upload,url,delete,folder" bare
                              data-library
                              @media:picked="noted($event.detail.paths)" />

        <x-admin.panel title="The library">
            <x-slot:acts>
                <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="open($event)">Open the library</button>
            </x-slot:acts>

            <div class="v-mediaroots">
                @foreach (\App\Support\MediaLibrary::ROOTS as $vRoot)
                    <button type="button" class="v-mediaroot" @click="open($event, '{{ $vRoot }}')">
                        <x-admin.icon name="folder" />
                        <span>{{ ucfirst($vRoot) }}</span>
                        <span class="mono">{{ number_format($counts[$vRoot] ?? 0) }}</span>
                    </button>
                @endforeach
            </div>
        </x-admin.panel>

        @if ($orphans['count'] > 0)
            <x-admin.panel title="Not used by anything">
                <x-slot:acts>
                    <span class="v-cell-quiet mono">{{ number_format($orphans['count']) }} of {{ number_format($orphans['total']) }}</span>
                </x-slot:acts>

                {{-- The retired OpenCart tree is still on disk and still
                     browsable beside the live one, with nothing marking it
                     retired. Naming the number is the first step to being
                     able to act on it. --}}
                <p class="v-cell-quiet">
                    {{ number_format($orphans['count']) }} image{{ $orphans['count'] === 1 ? '' : 's' }}
                    under <span class="mono">{{ $orphans['where'] }}</span>
                    {{ $orphans['count'] === 1 ? 'is' : 'are' }} referenced by no product.
                </p>

                <div class="v-actrow v-gap-sm">
                    <button type="button" class="v-btn v-btn--secondary v-btn--sm"
                            @click="open($event, '{{ $orphans['root'] }}')">Look through them</button>
                </div>
            </x-admin.panel>
        @endif
    </div>

    @push('scripts')
        <script>
            function mediaPage() {
                return {
                    /** Every root opens the one browser, aimed at that root. */
                    open(event, root) {
                        const el = document.querySelector('[data-library]');
                        if (!el) return;

                        const picker = window.Alpine.$data(el);
                        if (root) picker.root = root;
                        picker.value = [];

                        el.dispatchEvent(new CustomEvent('media:open', {
                            detail: { from: event.currentTarget },
                        }));
                    },

                    /** Nothing is being chosen here, so a selection is just a
                     *  selection; the panel's own delete is what acts on it. */
                    noted() {},
                };
            }
        </script>
    @endpush
</x-admin.shell>
