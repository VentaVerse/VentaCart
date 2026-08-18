<x-admin.shell :title="$group->name">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.banners.index') }}">Banner groups</a>
                <span aria-hidden="true">/</span>
                <span>{{ $group->name }}</span>
            </div>
            <h1 class="v-head__title">{{ $group->name }}</h1>
            <p class="v-head__standing">{{ $group->slug }} · {{ $group->autoplay ? 'Rotates every ' . $group->speed . 'ms' : 'Manual rotation' }}</p>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.banners.edit', $group) }}" class="v-btn v-btn--secondary">Group settings</a>
            <a href="{{ route('admin.banners.slides.create', $group) }}" class="v-btn v-btn--primary">New slide</a>
        </div>
    </div>

    <div class="v-settle">
        <x-admin.panel title="Slides" flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr><th>Slide</th><th>Showing</th><th>Status</th><th class="v-cell-act"><span class="v-sr">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($banners as $banner)
                            <tr>
                                <td>
                                    <span class="v-namerow">
                                        {{-- The slide's own background colour is a record's data,
                                             so an SVG rect carries it as a presentation attribute. --}}
                                        <span class="v-slidepeek">
                                            <svg viewBox="0 0 28 14" aria-hidden="true" focusable="false"><rect width="28" height="14" fill="{{ $banner->bg_color }}"/></svg>
                                            @if ($banner->bg_image)
                                                <img src="{{ $banner->bg_image_url }}" alt="">
                                            @endif
                                        </span>
                                        <span>
                                            <a href="{{ route('admin.banners.slides.edit', [$group, $banner]) }}" class="v-rowlink">{{ $banner->name ?: 'Untitled' }}</a>
                                            <span class="v-cell-quiet v-block">{{ count($banner->layers ?? []) }} {{ Str::plural('layer', count($banner->layers ?? [])) }}@if ($banner->height) &middot; {{ $banner->height }}px @endif</span>
                                        </span>
                                    </span>
                                </td>
                                <td>
                                    @if ($banner->starts_at || $banner->ends_at)
                                        {{ $banner->starts_at?->format('M d') ?? 'Now' }} to {{ $banner->ends_at?->format('M d') ?? 'no end' }}
                                    @else
                                        Always
                                    @endif
                                </td>
                                <td><span class="v-badge {{ $banner->is_active ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $banner->is_active ? 'Active' : 'Draft' }}</span></td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.banners.slides.edit', [$group, $banner]) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="submit" form="copy-slide-{{ $banner->id }}" class="v-btn v-btn--ghost v-btn--sm">Copy</button>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-slide-{{ $banner->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No slides yet</p>
                                        <p class="v-empty__line">A slide is one frame of this carousel.</p>
                                        <a href="{{ route('admin.banners.slides.create', $group) }}" class="v-btn v-btn--primary v-btn--sm">Add the first slide</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        @foreach ($banners as $banner)
            <form id="copy-slide-{{ $banner->id }}" method="POST" action="{{ route('admin.banners.slides.duplicate', [$group, $banner]) }}" hidden>@csrf</form>
            <x-admin.confirm name="delete-slide-{{ $banner->id }}" title="Delete slide"
                message="Delete this slide?" confirm="Delete"
                :action="route('admin.banners.slides.destroy', [$group, $banner])" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
