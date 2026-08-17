<x-admin3.shell title="Banners">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Banners</span>
            </div>
            <h1 class="v3-head__title">Banners</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.banners.create') }}" class="v3-btn v3-btn--primary">New group</a>
        </div>
    </div>

    <div class="v3-settle">
        <x-admin3.panel title="Groups" flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr><th>Group</th><th class="v3-cell-num">Slides</th><th>Rotation</th><th>Status</th><th class="v3-cell-act"><span class="v3-sr">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.banners.slides', $group) }}" class="v3-rowlink">{{ $group->name }}</a>
                                    <span class="v3-cell-quiet v3-block v3-mono">{{ $group->slug }}</span>
                                </td>
                                <td class="v3-cell-num v3-mono">{{ $group->slides_count }}</td>
                                <td>{{ $group->autoplay ? 'Every ' . $group->speed . 'ms' : 'Manual' }}</td>
                                <td><span class="v3-badge {{ $group->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $group->is_active ? 'Active' : 'Draft' }}</span></td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.banners.slides', $group) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Slides</a>
                                    <a href="{{ route('admin.banners.edit', $group) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Settings</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-group-{{ $group->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No banner groups yet</p>
                                        <p class="v3-empty__line">A group is one carousel placement, and its slides live inside it.</p>
                                        <a href="{{ route('admin.banners.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Create your first group</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        @foreach ($groups as $group)
            <x-admin3.confirm name="delete-group-{{ $group->id }}" title="Delete {{ $group->name }}?"
                message="Delete this group and all its slides?" confirm="Delete"
                :action="route('admin.banners.destroy', $group)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
