<x-admin.shell title="Email Templates">
    <x-admin.page title="Email Templates" :breadcrumb="[['label' => 'Settings', 'url' => route('admin.settings.index')]]">
        <x-admin.table>
            <x-slot:head>
                <x-admin.table.th>Name</x-admin.table.th>
                <x-admin.table.th>Slug</x-admin.table.th>
                <x-admin.table.th>Type</x-admin.table.th>
                <x-admin.table.th align="right">Status</x-admin.table.th>
                <x-admin.table.th align="right">Actions</x-admin.table.th>
            </x-slot:head>
            @forelse ($templates as $template)
                <x-admin.table.row href="{{ route('admin.email-templates.edit', $template) }}">
                    <x-admin.table.cell>
                        <span class="text-[13px] font-medium text-v-text">{{ $template->name }}</span>
                    </x-admin.table.cell>
                    <x-admin.table.cell mono>
                        <span class="text-xs text-v-muted">{{ $template->slug }}</span>
                    </x-admin.table.cell>
                    <x-admin.table.cell>
                        <x-admin.badge :tone="$template->type === 'customer' ? 'info' : 'neutral'">
                            {{ $template->type === 'customer' ? 'Customer' : 'Admin' }}
                        </x-admin.badge>
                    </x-admin.table.cell>
                    <x-admin.table.cell align="right">
                        <form method="POST" action="{{ route('admin.email-templates.toggle', $template) }}" data-v-form class="inline">
                            @csrf
                            <button type="submit"
                                class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $template->is_active ? 'bg-v-accent' : 'bg-v-strong' }}"
                                title="{{ $template->is_active ? 'Click to disable' : 'Click to enable' }}">
                                <span class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform {{ $template->is_active ? 'translate-x-4' : '' }}"></span>
                            </button>
                        </form>
                    </x-admin.table.cell>
                    <x-admin.table.cell align="right">
                        <a href="{{ route('admin.email-templates.edit', $template) }}" class="text-[13px] font-medium text-v-accent hover:opacity-80">Edit</a>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-2">
                        <x-admin.empty-state icon="pages" title="No email templates found." description="Run the seeder to create default templates." />
                    </td>
                </tr>
            @endforelse
        </x-admin.table>
    </x-admin.page>
</x-admin.shell>
