<x-admin.shell title="Banners">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Banners</span>
            </div>
            <h1 class="v-head__title">Banners</h1>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="banners" />
            <a href="{{ route('admin.banners.create') }}" class="v-btn v-btn--primary">New group</a>
        </div>
    </div>

    <div class="v-settle">
        <x-admin.panel title="Groups" flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr><th>Group</th><th class="v-cell-num">Slides</th><th>Rotation</th><th>Status</th><th class="v-cell-act"><span class="v-sr">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.banners.slides', $group) }}" class="v-rowlink">{{ $group->name }}</a>
                                    <span class="v-cell-quiet v-block v-mono">{{ $group->slug }}</span>
                                </td>
                                <td class="v-cell-num v-mono">{{ $group->slides_count }}</td>
                                <td>{{ $group->autoplay ? 'Every ' . $group->speed . 'ms' : 'Manual' }}</td>
                                <td><span class="v-badge {{ $group->is_active ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $group->is_active ? 'Active' : 'Draft' }}</span></td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.banners.slides', $group) }}" class="v-btn v-btn--ghost v-btn--sm">Slides</a>
                                    <a href="{{ route('admin.banners.edit', $group) }}" class="v-btn v-btn--ghost v-btn--sm">Settings</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-group-{{ $group->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No banner groups yet</p>
                                        <p class="v-empty__line">A group is one carousel placement, and its slides live inside it.</p>
                                        <a href="{{ route('admin.banners.create') }}" class="v-btn v-btn--primary v-btn--sm">Create your first group</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        @foreach ($groups as $group)
            <x-admin.confirm name="delete-group-{{ $group->id }}" title="Delete {{ $group->name }}?"
                message="Delete this group and all its slides?" confirm="Delete"
                :action="route('admin.banners.destroy', $group)" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>
