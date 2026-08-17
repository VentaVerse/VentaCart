<x-admin3.shell :title="$group ? 'Edit Group: ' . $group->name : 'New Banner Group'">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.banners.index') }}">Banner groups</a>
                <span aria-hidden="true">/</span>
                <span>{{ $group ? $group->name : 'New group' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $group ? $group->name : 'New banner group' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$group ? route('admin.banners.update', $group) : route('admin.banners.store')"
                   :method="$group ? 'PUT' : 'POST'" class="v3-settle">
        <x-admin3.panel title="Group">
            <div class="v3-grid v3-grid--2">
                <x-admin3.field name="name" label="Name" required>
                    <input type="text" name="name" value="{{ old('name', $group?->name) }}" required
                           placeholder="e.g. Homepage Hero" class="v3-control">
                </x-admin3.field>
                <x-admin3.field name="slug" label="Slug" required hint="Used to reference this group in the theme.">
                    <input type="text" name="slug" value="{{ old('slug', $group?->slug) }}" required
                           placeholder="e.g. homepage-hero" class="v3-control v3-mono">
                </x-admin3.field>
            </div>

            <div class="v3-gap">
                <div class="v3-grid v3-grid--3">
                    <x-admin3.field name="height" label="Height (px)" required>
                        <input type="number" name="height" value="{{ old('height', $group?->height ?? 500) }}" min="100" max="2000" required class="v3-control v3-mono">
                    </x-admin3.field>
                    <x-admin3.field name="height_mobile" label="Mobile height (px)" required>
                        <input type="number" name="height_mobile" value="{{ old('height_mobile', $group?->height_mobile ?? 400) }}" min="100" max="2000" required class="v3-control v3-mono">
                    </x-admin3.field>
                    <x-admin3.field name="speed" label="Autoplay speed (ms)" required>
                        <input type="number" name="speed" value="{{ old('speed', $group?->speed ?? 5000) }}" min="1000" max="30000" step="500" required class="v3-control v3-mono">
                    </x-admin3.field>
                </div>
            </div>

            <div class="v3-gap v3-actrow">
                <label class="v3-toggle">
                    <input type="hidden" name="full_width" value="0">
                    <input type="checkbox" name="full_width" value="1" @checked(old('full_width', $group?->full_width ?? true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Full width
                </label>
                <label class="v3-toggle">
                    <input type="hidden" name="autoplay" value="0">
                    <input type="checkbox" name="autoplay" value="1" @checked(old('autoplay', $group?->autoplay ?? true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Autoplay
                </label>
                <label class="v3-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $group?->is_active ?? true))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Active
                </label>
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.shell>
