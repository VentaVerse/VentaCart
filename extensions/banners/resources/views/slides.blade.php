<x-admin.shell :title="$group->name">
    <x-admin.page :title="$group->name"
        :standing="$group->slug . ' · ' . ($group->autoplay ? 'Rotates every ' . $group->speed . 'ms' : 'Manual rotation')">
        <x-slot:actions>
            <x-admin.button variant="secondary" href="{{ route('admin.banners.edit', $group) }}">Group settings</x-admin.button>
            <x-admin.button variant="primary" href="{{ route('admin.banners.slides.create', $group) }}">
                <x-admin.icon name="plus" class="w-4 h-4" /> New Slide
            </x-admin.button>
        </x-slot:actions>

        <x-admin.table roomy>
            <x-slot:head>
                <x-admin.table.th>Slide</x-admin.table.th>
                <x-admin.table.th>Showing</x-admin.table.th>
                <x-admin.table.th>Status</x-admin.table.th>
                <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
            </x-slot:head>
            @forelse ($banners as $banner)
                <x-admin.table.row href="{{ route('admin.banners.slides.edit', [$group, $banner]) }}">
                    <x-admin.table.cell>
                        <div class="flex items-center gap-3">
                            {{-- The slide's own background colour is a record's data,
                                 so it is bound rather than named in a stylesheet;
                                 an SVG rect carries it as a presentation attribute. --}}
                            <span class="relative block h-14 w-28 shrink-0 overflow-hidden rounded-v-md border border-v-border">
                                <svg class="absolute inset-0 h-full w-full" aria-hidden="true" focusable="false">
                                    <rect width="100%" height="100%" fill="{{ $banner->bg_color }}"></rect>
                                </svg>
                                @if ($banner->bg_image)
                                    <img src="{{ $banner->bg_image_url }}" alt="" class="relative h-full w-full object-cover">
                                @endif
                            </span>
                            <div class="min-w-0">
                                <div class="truncate text-[13px] font-medium text-v-text">{{ $banner->name ?: 'Untitled' }}</div>
                                {{-- A slide's own height is optional: null means it takes the
                                     group's, so it is printed only when the slide overrides. --}}
                                <div class="text-xs text-v-muted">{{ count($banner->layers ?? []) }} {{ Str::plural('layer', count($banner->layers ?? [])) }}@if ($banner->height) &middot; {{ $banner->height }}px @endif</div>
                            </div>
                        </div>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        @if ($banner->starts_at || $banner->ends_at)
                            {{ $banner->starts_at?->format('M d') ?? 'Now' }} to {{ $banner->ends_at?->format('M d') ?? 'no end' }}
                        @else
                            Always
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        <x-admin.badge :tone="$banner->is_active ? 'success' : 'neutral'">{{ $banner->is_active ? 'Active' : 'Draft' }}</x-admin.badge>
                    </x-admin.table.cell>
                    <x-admin.table.cell class="v-col-act">
                        <div class="v-rowactions v-rowactions--end">
                            <x-admin.button size="sm" variant="ghost" :href="route('admin.banners.slides.edit', [$group, $banner])">Edit</x-admin.button>
                            <form method="POST" action="{{ route('admin.banners.slides.duplicate', [$group, $banner]) }}" data-v-form class="inline">
                                @csrf
                                <x-admin.button size="sm" variant="ghost" type="submit">Copy</x-admin.button>
                            </form>
                            <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" @click="$dispatch('open-modal', 'delete-slide-{{ $banner->id }}')">Delete</x-admin.button>
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="4">
                        <x-admin.empty-state icon="media" title="No slides yet" description="A slide is one frame of this carousel.">
                            <x-admin.button variant="primary" :href="route('admin.banners.slides.create', $group)">Add the first slide</x-admin.button>
                        </x-admin.empty-state>
                    </td>
                </tr>
            @endforelse
        </x-admin.table>

        @foreach ($banners as $banner)
            <x-admin.confirm-dialog name="delete-slide-{{ $banner->id }}" title="Delete slide"
                message="Delete this slide?" confirm="Delete"
                :action="route('admin.banners.slides.destroy', [$group, $banner])" method="DELETE" />
        @endforeach
    </x-admin.page>
</x-admin.shell>
