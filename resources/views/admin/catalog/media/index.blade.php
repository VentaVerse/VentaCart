<x-admin.shell title="Media library" active="catalog">
    @push('head')
        @vite(['resources/css/media-library/manage.css', 'resources/js/media-library/manage.js'])
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.products.index') }}">Catalog</a>
                <span aria-hidden="true">/</span>
                <span>Media library</span>
            </div>
            <h1 class="v-head__title">Media library</h1>
        </div>
    </div>

    <div class="v-settle">
        {{-- The library itself: folders, images and the tools this person may
             use, drawn by the media library script from the settings that
             <x-media-library /> prints. --}}
        <x-media-library />
        <div id="media-library" class="ml-host" data-manage></div>

        @if ($unused['count'] > 0)
            <x-admin.panel title="Not used by anything">
                <x-slot:acts>
                    <span class="v-cell-quiet mono">{{ number_format($unused['count']) }} of {{ number_format($unused['total']) }}</span>
                </x-slot:acts>

                <p class="v-cell-quiet">
                    {{ number_format($unused['count']) }} image{{ $unused['count'] === 1 ? '' : 's' }}
                    in <span class="mono">{{ $unused['path'] }}</span>
                    {{ $unused['count'] === 1 ? 'is' : 'are' }} not used by anything in the store.
                </p>

                <div class="v-actrow v-gap-sm">
                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" data-media-go="{{ $unused['path'] }}">Look through them</button>
                </div>
            </x-admin.panel>
        @endif
    </div>
</x-admin.shell>
