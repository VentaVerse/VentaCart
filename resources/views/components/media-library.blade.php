{{--
    The media library for this page: its script, its styles and the settings
    it reads. Every image field includes it, so it prints once per page.
    A guest gets nothing, since every library request would be refused.
--}}
@auth
    @once
        @php
            $mediaUser = auth()->user();
            $mediaConfig = [
                'endpoints' => [
                    'browse' => route('admin.media.browse'),
                    'tree' => route('admin.media.tree'),
                    'upload' => route('admin.media.upload'),
                    'importUrl' => route('admin.media.import-url'),
                    'folder' => route('admin.media.folder'),
                    'rename' => route('admin.media.rename'),
                    'move' => route('admin.media.move'),
                    'delete' => route('admin.media.delete'),
                    'usage' => route('admin.media.usage'),
                ],
                'token' => csrf_token(),
                'can' => [
                    'view' => $mediaUser->hasPermission('media.view'),
                    'upload' => $mediaUser->hasPermission('media.create'),
                    'edit' => $mediaUser->hasPermission('media.edit'),
                    'delete' => $mediaUser->hasPermission('media.delete'),
                ],
                'maxBytes' => \App\Support\MediaLibrary::MAX_KB * 1024,
                'maxFiles' => \App\Support\MediaLibrary::MAX_FILES,
                'perPage' => \App\Support\MediaLibrary::PER_PAGE,
                'accept' => \App\Support\MediaLibrary::EXTENSIONS,
                'uploadAccept' => ['jpg', 'jpeg', 'png', 'webp'],
            ];
        @endphp
        @vite(['resources/css/media-library/media-library.css', 'resources/js/media-library/index.js'])
        <script type="application/json" id="media-library-config">@json($mediaConfig)</script>
    @endonce
@endauth
