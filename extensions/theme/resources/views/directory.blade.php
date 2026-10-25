<x-admin.shell title="Themes">
    @push('head')
        @vite('extensions/theme/resources/css/theme-bench.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Themes</span>
            </div>
            <h1 class="v-head__title">Themes</h1>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="theme" />
        </div>
    </div>

    <div class="v-themes">
        @foreach ($themes as $slug => $meta)
            <div class="v-theme {{ $slug === $active && $meta['installed'] ? 'is-active' : '' }} {{ $meta['installed'] ? '' : 'is-off' }}">
                <div class="v-theme__body">
                    <div class="v-theme__title">
                        <h2>{{ $meta['name'] }}</h2>
                        @if ($slug === $active && $meta['installed'])
                            <span class="v-theme__live">Live</span>
                        @elseif (! $meta['installed'] && empty($meta['update_available']))
                            <span class="v-tag">Not installed</span>
                        @endif
                        @if (! empty($meta['update_available']))
                            <span class="v-badge v-badge--warn">Update available</span>
                        @endif
                    </div>

                    @if (! empty($meta['update_available']))
                        <p class="v-theme__note">
                            Version {{ $meta['version'] ?? '' }} is on disk; version {{ $meta['installed_version'] }} is installed.
                            @if ($meta['installed'])
                                The storefront keeps running the installed theme until you update.
                            @else
                                The new files do not keep the theme contract, so the storefront shows a plain page. Update to see what is missing.
                            @endif
                        </p>
                    @endif

                    <dl class="v-theme__facts">
                        <div><dt>Folder</dt><dd>resources/themes/{{ $slug }}</dd></div>
                        <div><dt>Version</dt><dd>{{ ! empty($meta['update_available']) ? $meta['installed_version'] : ($meta['version'] ?? '1.0.0') }}</dd></div>
                        <div><dt>Colours</dt><dd>{{ count($meta['colors'] ?? []) }}</dd></div>
                        <div><dt>Regions</dt><dd>{{ count($meta['regions'] ?? []) }}</dd></div>
                    </dl>
                </div>

                <div class="v-theme__acts">
                    @if (! empty($meta['update_available']))
                        <form method="POST" action="{{ route('admin.theme.package.install') }}">
                            @csrf
                            <input type="hidden" name="theme" value="{{ $slug }}">
                            <button type="submit" class="v-btn v-btn--primary v-btn--sm">Update</button>
                        </form>
                    @endif
                    @if (! $meta['installed'] && ! empty($meta['update_available']))
                        {{-- Nothing else to offer until the update runs. --}}
                    @elseif (! $meta['installed'])
                        <form method="POST" action="{{ route('admin.theme.package.install') }}">
                            @csrf
                            <input type="hidden" name="theme" value="{{ $slug }}">
                            <button type="submit" class="v-btn v-btn--primary v-btn--sm">Install</button>
                        </form>
                    @elseif ($slug === $active)
                        @if (! empty($meta['editor']) && \Illuminate\Support\Facades\Route::has($meta['editor']))
                            <a href="{{ route($meta['editor']) }}" class="v-btn v-btn--primary v-btn--sm">Edit</a>
                        @else
                            <a href="{{ route('admin.theme.customise') }}" class="v-btn v-btn--primary v-btn--sm">Customise</a>
                            @if (empty($meta['editor']))
                                <a href="{{ route('admin.layout') }}" class="v-btn v-btn--sm">Arrange pages</a>
                            @endif
                            <a href="{{ route('admin.theme.preview') }}" class="v-btn v-btn--sm">Live preview</a>
                        @endif
                        <form method="POST" action="{{ route('admin.theme.package.uninstall') }}" x-data @submit.prevent="if (confirm('Uninstall the live theme? The storefront shows a plain page until another theme is made live.')) $el.submit()">
                            @csrf
                            <input type="hidden" name="theme" value="{{ $slug }}">
                            <button type="submit" class="v-btn v-btn--sm v-btn--danger">Uninstall</button>
                        </form>
                    @else
                        @if (! empty($meta['editor']) && \Illuminate\Support\Facades\Route::has($meta['editor']))
                            <a href="{{ route($meta['editor']) }}" class="v-btn v-btn--sm">Edit</a>
                        @else
                            <a href="{{ route('admin.theme.customise', ['theme' => $slug]) }}" class="v-btn v-btn--sm">Customise</a>
                            <a href="{{ route('admin.layout', ['theme' => $slug]) }}" class="v-btn v-btn--sm">Arrange pages</a>
                        @endif
                        <form method="POST" action="{{ route('admin.theme.activate') }}">
                            @csrf
                            <input type="hidden" name="theme" value="{{ $slug }}">
                            <button type="submit" class="v-btn v-btn--sm">Use this theme</button>
                        </form>
                        <form method="POST" action="{{ route('admin.theme.package.uninstall') }}">
                            @csrf
                            <input type="hidden" name="theme" value="{{ $slug }}">
                            <button type="submit" class="v-btn v-btn--sm">Uninstall</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-admin.shell>
