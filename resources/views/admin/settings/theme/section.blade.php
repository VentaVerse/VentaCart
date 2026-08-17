<x-admin3.settings-shell :title="'Edit ' . $section->title" active="theme">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.theme.index') }}">Theme</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index', ['tab' => 'homepage']) }}">Homepage</a>
                <span aria-hidden="true">/</span>
                <span>{{ $section->title }}</span>
            </div>
            <h1 class="v3-head__title">{{ $section->title }}
                <span class="v3-badge v3-badge--quiet">{{ $section->type }}</span>
            </h1>
        </div>
    </div>

    <x-admin3.form :action="route('admin.theme.sections.update', $section)" method="PUT" class="v3-settle">
        <x-admin3.panel title="Section">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="title" label="Title" required>
                    <input type="text" name="title" value="{{ old('title', $section->title) }}" required class="v3-control">
                </x-admin3.field>
                <div class="v3-addrow__opts">
                    <label class="v3-toggle">
                        <input type="hidden" name="is_enabled" value="0">
                        <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $section->is_enabled))>
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        Shown on the homepage
                    </label>
                </div>
            </div>
            <div class="v3-gap">
                @include('admin.settings.theme.section-config', ['section' => $section])
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.settings-shell>
