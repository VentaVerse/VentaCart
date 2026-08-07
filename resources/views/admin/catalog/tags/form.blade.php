<x-admin.shell :title="$tag ? 'Edit Tag' : 'New Tag'">
    <x-admin.page :title="$tag ? 'Edit Tag' : 'New Tag'">
        <div class="max-w-md">
            <x-admin.card>
                <form method="POST" action="{{ $tag ? route('admin.tags.update', $tag) : route('admin.tags.store') }}" data-v-form class="space-y-4">
                    @csrf
                    @if ($tag)
                        @method('PUT')
                    @endif

                    <x-admin.field name="name" label="Name" :help="$tag ? 'Slug: ' . $tag->slug : null">
                        <x-admin.input type="text" name="name" :value="$tag?->name" required />
                    </x-admin.field>

                    <x-admin.form-actions>
                        <x-admin.button variant="secondary" href="{{ route('admin.tags.index') }}">Cancel</x-admin.button>
                        <x-admin.button type="submit">{{ $tag ? 'Update' : 'Create' }}</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
