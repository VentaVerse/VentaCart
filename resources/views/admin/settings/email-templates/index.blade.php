<x-admin.settings-shell title="Email Templates" active="email-templates">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Email Templates</h1>
            <p class="v-head__standing">The content of every email the store sends. A switched-off template falls back to the built-in wording.</p>
        </div>
    </div>

    <x-admin.form :action="route('admin.email-templates.update-all')" method="PUT" class="v-settle">
        <x-admin.panel title="Templates" flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Type</th>
                            <th>Active</th>
                            <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.email-templates.edit', $template) }}" class="v-cell-link">{{ $template->name }}</a>
                                </td>
                                <td class="v-mono v-cell-quiet">{{ $template->slug }}</td>
                                <td>
                                    <span class="v-badge {{ $template->type === 'customer' ? 'v-badge--good' : 'v-badge--quiet' }}">
                                        {{ $template->type === 'customer' ? 'Customer' : 'Admin' }}
                                    </span>
                                </td>
                                <td>
                                    <label class="v-toggle">
                                        <input type="hidden" name="rows[{{ $template->id }}][is_active]" value="0">
                                        <input type="checkbox" name="rows[{{ $template->id }}][is_active]" value="1"
                                               @checked($template->is_active) aria-label="{{ $template->name }} active">
                                        <span class="v-toggle__track" aria-hidden="true"></span>
                                    </label>
                                </td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.email-templates.edit', $template) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="v-empty">
                                        <p class="v-empty__title">No email templates found</p>
                                        <p class="v-empty__line">Run the seeder to create the default set.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.settings-shell>
