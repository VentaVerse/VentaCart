<x-admin.shell :title="'Edit ' . $section->title">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index') }}">Theme</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.theme.index', ['tab' => 'homepage']) }}">Homepage</a>
                <span aria-hidden="true">/</span>
                <span>{{ $section->title }}</span>
            </div>
            <h1 class="v-head__title">{{ $section->title }}
                <span class="v-badge v-badge--quiet">{{ $section->type }}</span>
            </h1>
        </div>
    </div>

    <x-admin.form :action="route('admin.theme.sections.update', $section)" method="PUT" class="v-settle">
        <x-admin.panel title="Section">
            <div class="v-grid v-grid--2">
                <x-admin.field name="title" label="Title" required>
                    <input type="text" name="title" value="{{ old('title', $section->title) }}" required class="v-control">
                </x-admin.field>
                <div class="v-addrow__opts">
                    <label class="v-toggle">
                        <input type="hidden" name="is_enabled" value="0">
                        <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $section->is_enabled))>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        Shown on the homepage
                    </label>
                </div>
            </div>
            <div class="v-gap">
                @include('theme::section-config', ['section' => $section])
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
