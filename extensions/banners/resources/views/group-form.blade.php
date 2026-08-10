<x-admin.shell :title="$group ? 'Edit Group: ' . $group->name : 'New Banner Group'">
    <x-admin.page :title="$group ? 'Edit Group: ' . $group->name : 'New Banner Group'">
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('admin.banners.index') }}">Back to Groups</x-admin.button>
        </x-slot:actions>

        <x-admin.card class="max-w-2xl">
            <form method="POST" action="{{ $group ? route('admin.banners.update', $group) : route('admin.banners.store') }}" data-v-form class="space-y-6">
                @csrf
                @if ($group) @method('PUT') @endif

                <x-admin.field name="name" label="Name" required>
                    <x-admin.input name="name" :value="$group?->name" required placeholder="e.g. Homepage Hero" />
                </x-admin.field>

                <x-admin.field name="slug" label="Slug" help="Used to reference this group in the theme." required>
                    <x-admin.input name="slug" :value="$group?->slug" required class="font-mono" placeholder="e.g. homepage-hero" />
                </x-admin.field>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-admin.field name="height" label="Height (px)" required>
                        <x-admin.input type="number" name="height" :value="$group?->height ?? 500" min="100" max="2000" />
                    </x-admin.field>
                    <x-admin.field name="height_mobile" label="Mobile Height (px)" required>
                        <x-admin.input type="number" name="height_mobile" :value="$group?->height_mobile ?? 400" min="100" max="2000" />
                    </x-admin.field>
                    <x-admin.field name="speed" label="Autoplay Speed (ms)" required>
                        <x-admin.input type="number" name="speed" :value="$group?->speed ?? 5000" min="1000" max="30000" step="500" />
                    </x-admin.field>
                </div>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <x-admin.toggle name="full_width" label="Full Width" :checked="$group?->full_width ?? true" />
                    <x-admin.toggle name="autoplay" label="Autoplay" :checked="$group?->autoplay ?? true" />
                    <x-admin.toggle name="is_active" label="Active" :checked="$group?->is_active ?? true" />
                </div>

                <x-admin.form-actions>
                    <x-admin.button variant="secondary" href="{{ route('admin.banners.index') }}">Cancel</x-admin.button>
                    <x-admin.button type="submit">{{ $group ? 'Update Group' : 'Create Group' }}</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>
    </x-admin.page>
</x-admin.shell>
