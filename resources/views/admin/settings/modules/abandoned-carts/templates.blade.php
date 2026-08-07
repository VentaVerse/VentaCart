<x-admin.shell title="Abandoned Cart Templates">
    <x-admin.page title="Templates">
        <x-slot:actions>
            <x-admin.button href="{{ route('admin.abandoned-carts.templates.create') }}">Create Template</x-admin.button>
        </x-slot:actions>

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.sales.abandoned-carts.partials.nav')

            <div class="min-w-0 flex-1 space-y-4">
                <p class="text-[13px] text-v-secondary">Email templates used by abandoned cart notification rules. These are independent from the core email templates.</p>

                <x-admin.table>
                    <x-slot name="head">
                        <x-admin.table.th>Name</x-admin.table.th>
                        <x-admin.table.th>Subject</x-admin.table.th>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th align="right">Actions</x-admin.table.th>
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
                            <x-admin.table.cell align="right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.abandoned-carts.templates.edit', $template) }}" class="text-v-accent hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('admin.abandoned-carts.templates.test', $template) }}" data-v-form class="inline">
                                        @csrf
                                        <button type="submit" class="text-v-secondary hover:underline">Send Test</button>
                                    </form>
                                    <button type="button" class="text-v-danger hover:underline"
                                        @click="$dispatch('open-modal', 'delete-template-{{ $template->id }}')">Delete</button>
                                </div>
                                <x-admin.confirm-dialog name="delete-template-{{ $template->id }}" title="Delete template"
                                    message="Delete this template? This cannot be undone." confirm="Delete"
                                    :action="route('admin.abandoned-carts.templates.destroy', $template)" method="DELETE" />
                            </x-admin.table.cell>
                        </x-admin.table.row>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-2">
                                <x-admin.empty-state icon="cart" title="No templates found"
                                    description="Run: php artisan db:seed --class=AbandonedCartTemplateSeeder" />
                            </td>
                        </tr>
                    @endforelse
                </x-admin.table>
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>
