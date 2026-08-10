<x-admin.settings-shell title="Email Templates">
        <x-admin.table>
            <x-slot:head>
                <x-admin.table.th>Name</x-admin.table.th>
                <x-admin.table.th>Slug</x-admin.table.th>
                <x-admin.table.th>Type</x-admin.table.th>
                <x-admin.table.th align="right">Status</x-admin.table.th>
                <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
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
                            <x-admin.switch type="submit" :checked="$template->is_active" label="{{ $template->name }} active" />
                        </form>
                    </x-admin.table.cell>
                    <x-admin.table.cell class="v-col-act">
                        <div class="v-rowactions v-rowactions--end">
                            <x-admin.button size="sm" variant="ghost" :href="route('admin.email-templates.edit', $template)">Edit</x-admin.button>
                        </div>
                    </x-admin.table.cell>
                </x-admin.table.row>
            @empty
                <tr>
                    <td colspan="5">
                        <x-admin.empty-state title="No email templates found" description="Run the seeder to create the default set." />
                    </td>
                </tr>
            @endforelse
        </x-admin.table>
</x-admin.settings-shell>
