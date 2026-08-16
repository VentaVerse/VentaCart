<x-admin3.settings-shell title="Email Templates" active="email-templates">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Email Templates</h1>
            <p class="v3-head__standing">The content of every email the store sends. A switched-off template falls back to the built-in wording.</p>
        </div>
    </div>

    <x-admin3.form :action="route('admin.email-templates.update-all')" method="PUT" class="v3-settle">
        <x-admin3.panel title="Templates" flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Type</th>
                            <th>Active</th>
                            <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.email-templates.edit', $template) }}" class="v3-cell-link">{{ $template->name }}</a>
                                </td>
                                <td class="v3-mono v3-cell-quiet">{{ $template->slug }}</td>
                                <td>
                                    <span class="v3-badge {{ $template->type === 'customer' ? 'v3-badge--good' : 'v3-badge--quiet' }}">
                                        {{ $template->type === 'customer' ? 'Customer' : 'Admin' }}
                                    </span>
                                </td>
                                <td>
                                    <label class="v3-toggle">
                                        <input type="hidden" name="rows[{{ $template->id }}][is_active]" value="0">
                                        <input type="checkbox" name="rows[{{ $template->id }}][is_active]" value="1"
                                               @checked($template->is_active) aria-label="{{ $template->name }} active">
                                        <span class="v3-toggle__track" aria-hidden="true"></span>
                                    </label>
                                </td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.email-templates.edit', $template) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v3-empty">
                                        <p class="v3-empty__title">No email templates found</p>
                                        <p class="v3-empty__line">Run the seeder to create the default set.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.settings-shell>
