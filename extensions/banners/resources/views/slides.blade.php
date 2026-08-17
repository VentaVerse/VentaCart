<x-admin3.shell :title="$group->name">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.banners.index') }}">Banner groups</a>
                <span aria-hidden="true">/</span>
                <span>{{ $group->name }}</span>
            </div>
            <h1 class="v3-head__title">{{ $group->name }}</h1>
            <p class="v3-head__standing">{{ $group->slug }} · {{ $group->autoplay ? 'Rotates every ' . $group->speed . 'ms' : 'Manual rotation' }}</p>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.banners.edit', $group) }}" class="v3-btn v3-btn--secondary">Group settings</a>
            <a href="{{ route('admin.banners.slides.create', $group) }}" class="v3-btn v3-btn--primary">New slide</a>
        </div>
    </div>

    <div class="v3-settle">
        <x-admin3.panel title="Slides" flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr><th>Slide</th><th>Showing</th><th>Status</th><th class="v3-cell-act"><span class="v3-sr">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($banners as $banner)
                            <tr>
                                <td>
                                    <span class="v3-namerow">
                                        {{-- The slide's own background colour is a record's data,
                                             so an SVG rect carries it as a presentation attribute. --}}
                                        <span class="v3-slidepeek">
                                            <svg viewBox="0 0 28 14" aria-hidden="true" focusable="false"><rect width="28" height="14" fill="{{ $banner->bg_color }}"/></svg>
                                            @if ($banner->bg_image)
                                                <img src="{{ $banner->bg_image_url }}" alt="">
                                            @endif
                                        </span>
                                        <span>
                                            <a href="{{ route('admin.banners.slides.edit', [$group, $banner]) }}" class="v3-rowlink">{{ $banner->name ?: 'Untitled' }}</a>
                                            <span class="v3-cell-quiet v3-block">{{ count($banner->layers ?? []) }} {{ Str::plural('layer', count($banner->layers ?? [])) }}@if ($banner->height) &middot; {{ $banner->height }}px @endif</span>
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
                                <td><span class="v3-badge {{ $banner->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $banner->is_active ? 'Active' : 'Draft' }}</span></td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.banners.slides.edit', [$group, $banner]) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="submit" form="copy-slide-{{ $banner->id }}" class="v3-btn v3-btn--ghost v3-btn--sm">Copy</button>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-slide-{{ $banner->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No slides yet</p>
                                        <p class="v3-empty__line">A slide is one frame of this carousel.</p>
                                        <a href="{{ route('admin.banners.slides.create', $group) }}" class="v3-btn v3-btn--primary v3-btn--sm">Add the first slide</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        @foreach ($banners as $banner)
            <form id="copy-slide-{{ $banner->id }}" method="POST" action="{{ route('admin.banners.slides.duplicate', [$group, $banner]) }}" hidden>@csrf</form>
            <x-admin3.confirm name="delete-slide-{{ $banner->id }}" title="Delete slide"
                message="Delete this slide?" confirm="Delete"
                :action="route('admin.banners.slides.destroy', [$group, $banner])" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
