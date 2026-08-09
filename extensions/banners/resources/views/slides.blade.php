<x-admin.shell :title="$group->name">
    <x-admin.page :title="$group->name">
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('admin.banners.index') }}">All Groups</x-admin.button>
            <x-admin.button variant="primary" href="{{ route('admin.banners.slides.create', $group) }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> New Slide
            </x-admin.button>
        </x-slot:actions>

        <div class="mb-4 flex items-center gap-3 text-[13px] text-v-secondary">
            <span class="font-mono text-xs bg-v-sunken px-2 py-0.5 rounded-v-sm">{{ $group->slug }}</span>
            <span>{{ $group->autoplay ? 'Autoplay ' . $group->speed . 'ms' : 'Manual' }}</span>
            <a href="{{ route('admin.banners.edit', $group) }}" class="text-v-accent hover:opacity-80">Settings</a>
        </div>

        <div class="bg-v-surface border border-v-border rounded-v-lg">
            @if ($banners->isEmpty())
                <x-admin.empty-state icon="media" title="No slides yet" description='Click "New Slide" to add a slide to this group' />
            @else
                <div class="divide-y divide-v-border">
                    @foreach ($banners as $banner)
                        <div class="flex items-center gap-4 px-6 py-3 hover:bg-v-hover group">
                            <div class="w-40 h-20 rounded-v-lg overflow-hidden border border-v-border shrink-0"
                                 style="background: {{ $banner->bg_color }}">
                                @if ($banner->bg_image)
                                    <img src="{{ $banner->bg_image_url }}" alt="{{ $banner->name }}" class="w-full h-full object-cover">
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[13px] font-medium text-v-text">{{ $banner->name ?: 'Untitled' }}</p>
                                <p class="text-xs text-v-muted">{{ count($banner->layers ?? []) }} layers &middot; {{ $banner->height }}px</p>
                            </div>
                            <div class="text-xs text-v-muted shrink-0">
                                @if ($banner->starts_at || $banner->ends_at)
                                    {{ $banner->starts_at?->format('M d') ?? 'Now' }} to {{ $banner->ends_at?->format('M d') ?? 'no end' }}
                                @else
                                    Always
                                @endif
                            </div>
                            <x-admin.badge :tone="$banner->is_active ? 'success' : 'neutral'">{{ $banner->is_active ? 'Active' : 'Draft' }}</x-admin.badge>
                            <div class="flex items-center gap-3 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="{{ route('admin.banners.slides.edit', [$group, $banner]) }}" class="text-xs text-v-accent hover:opacity-80">Edit</a>
                                <form method="POST" action="{{ route('admin.banners.slides.duplicate', [$group, $banner]) }}" data-v-form class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs text-v-secondary hover:text-v-text">Copy</button>
                                </form>
                                <button type="button" class="text-xs text-v-danger hover:opacity-80" @click="$dispatch('open-modal', 'delete-slide-{{ $banner->id }}')">Delete</button>
                                <x-admin.confirm-dialog name="delete-slide-{{ $banner->id }}" title="Delete slide"
                                    message="Delete this slide?" confirm="Delete"
                                    :action="route('admin.banners.slides.destroy', [$group, $banner])" method="DELETE" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-admin.page>
</x-admin.shell>
