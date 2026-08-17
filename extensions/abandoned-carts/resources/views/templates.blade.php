<x-admin3.shell title="Recovery Templates">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Abandoned Carts</span>
                <span aria-hidden="true">/</span>
                <span>Templates</span>
            </div>
            <h1 class="v3-head__title">Abandoned Carts</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.abandoned-carts.templates.create') }}" class="v3-btn v3-btn--primary">Create template</a>
        </div>
    </div>

    @include('abandoned-carts::partials.config-tabs')

    <div class="v3-settle">
        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr><th>Name</th><th>Subject</th><th>Status</th><th class="v3-cell-act"><span class="v3-sr">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.abandoned-carts.templates.edit', $template) }}" class="v3-rowlink">{{ $template->name }}</a>
                                    <span class="v3-cell-quiet v3-block v3-mono">{{ $template->slug }}</span>
                                </td>
                                <td class="v3-cell-quiet">{{ $template->subject }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.abandoned-carts.templates.toggle', $template) }}" class="v3-inline">
                                        @csrf
                                        <button type="submit" class="v3-badgebtn" title="{{ $template->is_active ? 'Deactivate' : 'Activate' }}">
                                            <span class="v3-badge {{ $template->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span>
                                        </button>
                                    </form>
                                </td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.abandoned-carts.templates.edit', $template) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" x-data="{ sending: false }" :disabled="sending"
                                            @click="sending = true; v3Post(@js(route('admin.abandoned-carts.templates.test', $template))).finally(() => sending = false)"
                                            x-text="sending ? 'Sending…' : 'Send test'">Send test</button>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-template-{{ $template->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="v3-empty"><p class="v3-empty__title">No templates found</p><p class="v3-empty__line">Run the AbandonedCartTemplateSeeder to create the default set.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        @foreach ($templates as $template)
            <x-admin3.confirm name="delete-template-{{ $template->id }}" title="Delete template?"
                message="Delete this template? This cannot be undone." confirm="Delete"
                :action="route('admin.abandoned-carts.templates.destroy', $template)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.shell>
