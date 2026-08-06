<x-admin.shell title="Banners">
    <x-admin.page title="Banners" :breadcrumb="[['label' => 'Extensions']]">
        <x-slot:actions>
            <x-admin.button variant="primary" href="{{ route('admin.banners.create') }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> New Group
            </x-admin.button>
        </x-slot:actions>

        <p class="text-[13px] text-v-secondary mb-4">Each group is a carousel placement. Add slides inside a group.</p>

        <div class="bg-v-surface border border-v-border rounded-v-lg">
            @if ($groups->isEmpty())
                <x-admin.empty-state icon="media" title="No banner groups yet" description='Click "New Group" to create your first banner carousel' />
            @else
                <div class="divide-y divide-v-border">
                    @foreach ($groups as $group)
                        <div class="flex items-center gap-4 px-6 py-4 hover:bg-v-hover group">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('admin.banners.slides', $group) }}" class="text-[13px] font-medium text-v-text hover:text-v-accent">{{ $group->name }}</a>
                                <p class="text-xs text-v-muted font-mono mt-0.5">{{ $group->slug }}</p>
                            </div>
                            <div class="text-xs text-v-secondary shrink-0">
                                {{ $group->slides_count }} {{ Str::plural('slide', $group->slides_count) }}
                            </div>
                            <div class="text-xs text-v-muted shrink-0">
                                {{ $group->autoplay ? $group->speed . 'ms' : 'Manual' }}
                            </div>
                            <x-admin.badge :tone="$group->is_active ? 'success' : 'neutral'">{{ $group->is_active ? 'Active' : 'Draft' }}</x-admin.badge>
                            <div class="flex items-center gap-3 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="{{ route('admin.banners.slides', $group) }}" class="text-xs text-v-accent hover:opacity-80">Slides</a>
                                <a href="{{ route('admin.banners.edit', $group) }}" class="text-xs text-v-secondary hover:text-v-text">Settings</a>
                                <button type="button" class="text-xs text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-group-{{ $group->id }}')">Delete</button>
                                <x-admin.confirm-dialog name="delete-group-{{ $group->id }}" title="Delete banner group"
                                    message="Delete this group and all its slides?" confirm="Delete"
                                    :action="route('admin.banners.destroy', $group)" method="DELETE" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-admin.page>
</x-admin.shell>
