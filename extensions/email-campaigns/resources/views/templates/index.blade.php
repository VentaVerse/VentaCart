<x-admin.shell title="Email templates">
    @push('head')
        @vite('extensions/email-campaigns/resources/css/email-campaigns.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.email-campaigns.index') }}">Email</a>
                <span aria-hidden="true">/</span>
                <span>Templates</span>
            </div>
            <h1 class="v-head__title">Email templates</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.email-campaigns.templates.create') }}" class="v-btn v-btn--primary">New template</a>
        </div>
    </div>

    <div class="v-settle">
        <div class="v-tablewrap">
            <table class="v-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Subject</th>
                        <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr>
                            <td><a href="{{ route('admin.email-campaigns.templates.edit', $template) }}" class="v-rowlink">{{ $template->name }}</a></td>
                            <td class="v-cell-quiet">{{ $template->subject }}</td>
                            <td class="v-cell-act">
                                <a href="{{ route('admin.email-campaigns.templates.edit', $template) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                <button type="button" class="v-btn v-btn--danger v-btn--sm"
                                        @click="$dispatch('v-confirm', 'delete-template-{{ $template->id }}')">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <div class="v-empty">
                                    <p class="v-empty__line">No saved templates yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.pager :paginator="$templates" />
    </div>

    @foreach ($templates as $template)
        <x-admin.confirm name="delete-template-{{ $template->id }}" title="Delete &ldquo;{{ $template->name }}&rdquo;?"
            message="This permanently removes the saved template. This cannot be undone."
            confirm="Delete" :action="route('admin.email-campaigns.templates.destroy', $template)" method="DELETE" />
    @endforeach
</x-admin.shell>
