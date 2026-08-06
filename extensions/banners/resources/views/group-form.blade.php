<x-admin.shell :title="$group ? 'Edit Group: ' . $group->name : 'New Banner Group'">
    <x-admin.page :title="$group ? 'Edit Group: ' . $group->name : 'New Banner Group'"
        :breadcrumb="[['label' => 'Banners', 'url' => route('admin.banners.index')]]">
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('admin.banners.index') }}">Back to Groups</x-admin.button>
        </x-slot:actions>

        <x-admin.card class="max-w-2xl">
            <form method="POST" action="{{ $group ? route('admin.banners.update', $group) : route('admin.banners.store') }}" data-v-form class="space-y-6">
                @csrf
                @if ($group) @method('PUT') @endif

                <x-admin.field name="name" label="Name">
                    <x-admin.input name="name" :value="$group?->name" required placeholder="e.g. Homepage Hero" />
                </x-admin.field>

                <x-admin.field name="slug" label="Slug" help="Used to reference this group in the theme.">
                    <x-admin.input name="slug" :value="$group?->slug" required class="font-mono" placeholder="e.g. homepage-hero" />
                </x-admin.field>

                <div class="grid grid-cols-3 gap-4">
                    <x-admin.field name="height" label="Height (px)">
                        <x-admin.input type="number" name="height" :value="$group?->height ?? 500" min="100" max="2000" />
                    </x-admin.field>
                    <x-admin.field name="height_mobile" label="Mobile Height (px)">
                        <x-admin.input type="number" name="height_mobile" :value="$group?->height_mobile ?? 400" min="100" max="2000" />
                    </x-admin.field>
                    <x-admin.field name="speed" label="Autoplay Speed (ms)">
                        <x-admin.input type="number" name="speed" :value="$group?->speed ?? 5000" min="1000" max="30000" step="500" />
                    </x-admin.field>
                </div>

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-[13px] text-v-text cursor-pointer">
                        <x-admin.toggle name="full_width" :checked="$group?->full_width ?? true" />
                        Full Width
                    </label>
                    <label class="flex items-center gap-2 text-[13px] text-v-text cursor-pointer">
                        <x-admin.toggle name="autoplay" :checked="$group?->autoplay ?? true" />
                        Autoplay
                    </label>
                    <label class="flex items-center gap-2 text-[13px] text-v-text cursor-pointer">
                        <x-admin.toggle name="is_active" :checked="$group?->is_active ?? true" />
                        Active
                    </label>
                </div>

                <x-admin.form-actions>
                    <x-admin.button variant="secondary" href="{{ route('admin.banners.index') }}">Cancel</x-admin.button>
                    <x-admin.button type="submit">{{ $group ? 'Update Group' : 'Create Group' }}</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </x-admin.page>
</x-admin.shell>
