<x-admin.shell :title="$group ? 'Edit Group: ' . $group->name : 'New Banner Group'">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.banners.index') }}">Banners</a>
                <span aria-hidden="true">/</span>
                <span>{{ $group ? $group->name : 'New group' }}</span>
            </div>
            <h1 class="v-head__title">{{ $group ? $group->name : 'New banner group' }}</h1>
        </div>
    </div>

    {{-- multipart, because the create form now carries the first image. Without
         it the file silently never arrives and the banner is created empty. --}}
    <x-admin.form :action="$group ? route('admin.banners.update', $group) : route('admin.banners.store')"
                   :method="$group ? 'PUT' : 'POST'" class="v-settle"
                   enctype="multipart/form-data">
        <x-admin.panel title="Banner">
            <div class="v-grid v-grid--2">
                <x-admin.field name="name" label="Name" required>
                    <input type="text" name="name" value="{{ old('name', $group?->name) }}" required
                           placeholder="e.g. Homepage Hero" class="v-control">
                </x-admin.field>
                <x-admin.field name="slug" label="Reference"
                               hint="Left blank, this is made from the name.">
                    <input type="text" name="slug" value="{{ old('slug', $group?->slug) }}"
                           placeholder="homepage-hero" class="v-control v-mono">
                </x-admin.field>
            </div>

            @unless ($group)
                {{-- The picture, on the page where a merchant came to add a
                     picture. Without this, creating a banner meant a group, then
                     the group's list, then a slide, then the layer editor. --}}
                <div class="v-gap">
                    <div class="v-grid v-grid--2">
                        <x-admin.field name="image" label="Image">
                            <input type="file" name="image" accept="image/*" class="v-control">
                        </x-admin.field>
                        <x-admin.field name="link" label="Links to" hint="Where a click on it goes.">
                            <input type="text" name="link" value="{{ old('link') }}"
                                   placeholder="/category/guitars" class="v-control">
                        </x-admin.field>
                    </div>
                </div>
            @endunless

            <div class="v-gap">
                <div class="v-grid v-grid--3">
                    <x-admin.field name="height" label="Height (px)" required>
                        <input type="number" name="height" value="{{ old('height', $group?->height ?? 500) }}" min="100" max="2000" required class="v-control v-mono">
                    </x-admin.field>
                    <x-admin.field name="height_mobile" label="Mobile height (px)" required>
                        <input type="number" name="height_mobile" value="{{ old('height_mobile', $group?->height_mobile ?? 400) }}" min="100" max="2000" required class="v-control v-mono">
                    </x-admin.field>
                    <x-admin.field name="speed" label="Autoplay speed (ms)" required>
                        <input type="number" name="speed" value="{{ old('speed', $group?->speed ?? 5000) }}" min="1000" max="30000" step="500" required class="v-control v-mono">
                    </x-admin.field>
                </div>
            </div>

            <div class="v-gap v-actrow">
                <label class="v-toggle">
                    <input type="hidden" name="full_width" value="0">
                    <input type="checkbox" name="full_width" value="1" @checked(old('full_width', $group?->full_width ?? true))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Full width
                </label>
                <label class="v-toggle">
                    <input type="hidden" name="autoplay" value="0">
                    <input type="checkbox" name="autoplay" value="1" @checked(old('autoplay', $group?->autoplay ?? true))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Autoplay
                </label>
                <label class="v-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $group?->is_active ?? true))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    Active
                </label>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>
