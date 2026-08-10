<x-admin.settings-shell title="Templates" :wide="true">
        <x-slot:actions>
            <x-admin.button href="{{ route('admin.abandoned-carts.templates.create') }}">Create Template</x-admin.button>
        </x-slot:actions>

        @include('admin.settings.modules.abandoned-carts.partials.nav')

        <div class="space-y-4">
                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Name</x-admin.table.th>
                        <x-admin.table.th>Subject</x-admin.table.th>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                    </x-slot>
                    @forelse ($templates as $template)
                        <x-admin.table.row>
                            <x-admin.table.cell>
                                <div class="font-medium text-v-text">{{ $template->name }}</div>
                                <div class="v-num text-xs text-v-muted">{{ $template->slug }}</div>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $template->subject }}</x-admin.table.cell>
                            <x-admin.table.cell>
                                <form method="POST" action="{{ route('admin.abandoned-carts.templates.toggle', $template) }}" data-v-form class="inline">
                                    @csrf
                                    <button type="submit">
                                        <x-admin.badge :tone="$template->is_active ? 'success' : 'neutral'" class="cursor-pointer">
                                            {{ $template->is_active ? 'Active' : 'Inactive' }}
                                        </x-admin.badge>
                                    </button>
                                </form>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="v-col-act">
                                <div class="v-rowactions v-rowactions--end">
                                    <x-admin.button size="sm" variant="ghost" :href="route('admin.abandoned-carts.templates.edit', $template)">Edit</x-admin.button>
                                    <form method="POST" action="{{ route('admin.abandoned-carts.templates.test', $template) }}" data-v-form class="inline">
                                        @csrf
                                        <x-admin.button size="sm" variant="ghost" type="submit">Send test</x-admin.button>
                                    </form>
                                    <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" type="button"
                                        @click="$dispatch('open-modal', 'delete-template-{{ $template->id }}')">Delete</x-admin.button>
                                </div>
                                <x-admin.confirm-dialog name="delete-template-{{ $template->id }}" title="Delete template"
                                    message="Delete this template? This cannot be undone." confirm="Delete"
                                    :action="route('admin.abandoned-carts.templates.destroy', $template)" method="DELETE" />
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-admin.empty-state title="No templates found"
                                    description="Run the AbandonedCartTemplateSeeder to create the default set." />
                            </td>
                        </tr>
                    @endforelse
                </x-admin.table>
        </div>
</x-admin.settings-shell>
