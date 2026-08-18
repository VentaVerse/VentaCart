<x-abandoned-carts::space title="Recovery Templates" active="templates">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Abandoned Carts</span>
                <span aria-hidden="true">/</span>
                <span>Templates</span>
            </div>
            <h1 class="v-head__title">Abandoned Carts</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.abandoned-carts.templates.create') }}" class="v-btn v-btn--primary">Create template</a>
        </div>
    </div>


    <div class="v-settle">
        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr><th>Name</th><th>Subject</th><th>Status</th><th class="v-cell-act"><span class="v-sr">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.abandoned-carts.templates.edit', $template) }}" class="v-rowlink">{{ $template->name }}</a>
                                    <span class="v-cell-quiet v-block v-mono">{{ $template->slug }}</span>
                                </td>
                                <td class="v-cell-quiet">{{ $template->subject }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.abandoned-carts.templates.toggle', $template) }}" class="v-inline">
                                        @csrf
                                        <button type="submit" class="v-badgebtn" title="{{ $template->is_active ? 'Deactivate' : 'Activate' }}">
                                            <span class="v-badge {{ $template->is_active ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span>
                                        </button>
                                    </form>
                                </td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.abandoned-carts.templates.edit', $template) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--ghost v-btn--sm" x-data="{ sending: false }" :disabled="sending"
                                            @click="sending = true; vPost(@js(route('admin.abandoned-carts.templates.test', $template))).finally(() => sending = false)"
                                            x-text="sending ? 'Sending…' : 'Send test'">Send test</button>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-template-{{ $template->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="v-empty"><p class="v-empty__title">No templates found</p><p class="v-empty__line">Run the AbandonedCartTemplateSeeder to create the default set.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        @foreach ($templates as $template)
            <x-admin.confirm name="delete-template-{{ $template->id }}" title="Delete template?"
                message="Delete this template? This cannot be undone." confirm="Delete"
                :action="route('admin.abandoned-carts.templates.destroy', $template)" method="DELETE" />
        @endforeach
    </div>
</x-abandoned-carts::space>
