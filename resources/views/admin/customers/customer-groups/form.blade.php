<x-admin.shell :title="$group ? 'Edit Customer Group' : 'Create Customer Group'">
    <x-admin.page :title="$group ? 'Edit Customer Group' : 'Create Customer Group'">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.customer-groups.index') }}">Back to Groups</x-admin.button>
        </x-slot:actions>

        <div class="max-w-2xl">
            <x-admin.card>
                <form method="POST"
                      action="{{ $group ? route('admin.customer-groups.update', $group) : route('admin.customer-groups.store') }}"
                      data-v-form class="space-y-6">
                    @csrf
                    @if ($group)
                        @method('PUT')
                    @endif
                    <input type="hidden" name="store_id" value="1">

                    <x-admin.field name="name" label="Name" required>
                        <x-admin.input type="text" name="name" required :value="$group?->name" />
                    </x-admin.field>

                    <x-admin.field name="description" label="Description">
                        <x-admin.textarea name="description" rows="3" :value="$group?->description" />
                    </x-admin.field>

                    <div class="flex items-center gap-2">
                        <x-admin.toggle name="is_default" :checked="$group?->is_default ?? false" label="Default group for new customers" />
                        <x-admin.hint label="About the default group">Only one group can be the default. Setting this unsets the current one.</x-admin.hint>
                    </div>

                    <x-admin.form-actions>
                        <x-admin.button variant="secondary" href="{{ route('admin.customer-groups.index') }}">Cancel</x-admin.button>
                        <x-admin.button type="submit">{{ $group ? 'Update Group' : 'Create Group' }}</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
