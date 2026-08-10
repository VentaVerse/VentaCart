<x-admin.settings-shell title="Stock Alerts">
        <div class="v-stats mb-6">
            <x-admin.figure label="Waiting" :value="$totalWaiting" accent="amber" icon="cart" />
            <x-admin.figure label="Notified" :value="$totalNotified" accent="green" icon="customers" />
            <x-admin.figure label="Failed" :value="$totalFailed" accent="rose" icon="warning" />
        </div>

        <x-admin.tabs default="{{ $tab }}" param="tab" :names="['waitlist', 'notified', 'template']">
            <x-slot:nav>
                <x-admin.tabs.tab name="waitlist">Waitlist</x-admin.tabs.tab>
                <x-admin.tabs.tab name="notified">Notified</x-admin.tabs.tab>
                <x-admin.tabs.tab name="template">Email Template</x-admin.tabs.tab>
            </x-slot:nav>

            {{-- ==================== WAITLIST ==================== --}}
            <x-admin.tabs.panel name="waitlist">
                <x-admin.card>
                    <form method="GET" class="flex gap-3 mb-4">
                        <input type="hidden" name="tab" value="waitlist">
                        <x-admin.input name="search" :value="$search" aria-label="Search the waitlist" placeholder="Search product, name, or email..." class="flex-1" />
                        <x-admin.button type="submit">Search</x-admin.button>
                        @if ($search)
                            <x-admin.button variant="secondary" href="{{ route('admin.stock-notifications.index', ['tab' => 'waitlist']) }}">Clear</x-admin.button>
                        @endif
                    </form>

                    <div x-data="{ selected: [] }">
                        <div class="flex items-center justify-between mb-3">
                            <x-admin.button variant="danger" size="sm" type="button" x-show="selected.length > 0" x-cloak
                                @click="$dispatch('open-modal', 'bulk-delete-waitlist')">
                                Delete <span x-text="selected.length"></span> selected
                            </x-admin.button>
                        </div>

                        <x-admin.table>
                            <x-slot:head>
                                <x-admin.table.th class="w-8">
                                    <label class="v-check v-check--bare"><input type="checkbox" aria-label="Select every row" @change="selected = $event.target.checked ? [{{ $waitlist->pluck('id')->join(',') }}] : []" class="w-4 h-4 rounded-v-sm border bg-v-surface text-v-accent focus:ring-v-accent"></label>
                                </x-admin.table.th>
                                <x-admin.table.th>Customer</x-admin.table.th>
                                <x-admin.table.th>Email</x-admin.table.th>
                                <x-admin.table.th>Product</x-admin.table.th>
                                <x-admin.table.th>Variant</x-admin.table.th>
                                <x-admin.table.th>Requested</x-admin.table.th>
                                <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                            </x-slot:head>
                            @forelse ($waitlist as $item)
                                <x-admin.table.row>
                                    <x-admin.table.cell>
                                        <label class="v-check v-check--bare"><input type="checkbox" :value="{{ $item->id }}" x-model.number="selected" aria-label="Select {{ $item->email }}" class="w-4 h-4 rounded-v-sm border bg-v-surface text-v-accent focus:ring-v-accent"></label>
                                    </x-admin.table.cell>
                                    <x-admin.table.cell class="text-v-text">{{ $item->name }}</x-admin.table.cell>
                                    <x-admin.table.cell class="text-v-secondary">{{ $item->email }}</x-admin.table.cell>
                                    <x-admin.table.cell class="text-v-text">{{ $item->product?->name ?? 'Deleted' }}</x-admin.table.cell>
                                    <x-admin.table.cell class="text-v-secondary">{{ $item->variant?->name ?? '-' }}</x-admin.table.cell>
                                    <x-admin.table.cell class="text-v-muted">{{ $item->created_at->format('M j, Y') }}</x-admin.table.cell>
                                    <x-admin.table.cell class="v-col-act">
                                        <div class="v-rowactions v-rowactions--end">
                                            <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" type="button"
                                                @click="$dispatch('open-modal', 'delete-waitlist-{{ $item->id }}')">Delete</x-admin.button>
                                        </div>
                                        <x-admin.confirm-dialog name="delete-waitlist-{{ $item->id }}" title="Remove notification"
                                            message="Remove this subscriber from the waitlist?" confirm="Delete"
                                            :action="route('admin.stock-notifications.destroy', $item)" method="DELETE" />
                                    </x-admin.table.cell>
                                </x-admin.table.row>
                            @empty
                                <tr><td colspan="7"><x-admin.empty-state title="No subscribers waiting" /></td></tr>
                            @endforelse

                            <x-slot:footer>
                                @if ($waitlist->hasPages())
                                    {{ $waitlist->links() }}
                                @endif
                            </x-slot:footer>
                        </x-admin.table>

                        <x-admin.modal name="bulk-delete-waitlist" title="Delete selected notifications?" maxWidth="sm">
                            <p class="text-[13px] text-v-secondary">Delete <span x-text="selected.length"></span> selected notification(s)? This cannot be undone.</p>
                            <form method="POST" action="{{ route('admin.stock-notifications.bulk-delete') }}" data-v-form class="mt-4 flex justify-end gap-2">
                                @csrf
                                <template x-for="id in selected" :key="id">
                                    <input type="hidden" name="ids[]" :value="id">
                                </template>
                                <x-admin.button variant="secondary" type="button" @click="open = false">Cancel</x-admin.button>
                                <x-admin.button variant="danger" type="submit">Delete Selected</x-admin.button>
                            </form>
                        </x-admin.modal>
                    </div>
                </x-admin.card>

                {{-- Analytics: Most Requested --}}
                @if ($topProducts->isNotEmpty())
                    <x-admin.card title="Most Requested Products" class="mt-6">
                        <x-admin.table>
                            <x-slot:head>
                                <x-admin.table.th>Product</x-admin.table.th>
                                <x-admin.table.th>Total Requests</x-admin.table.th>
                                <x-admin.table.th>Currently Waiting</x-admin.table.th>
                            </x-slot:head>
                            @foreach ($topProducts as $tp)
                                <x-admin.table.row>
                                    <x-admin.table.cell class="text-v-text">{{ $tp->name }}</x-admin.table.cell>
                                    <x-admin.table.cell class="text-v-secondary">{{ $tp->total_requests }}</x-admin.table.cell>
                                    <x-admin.table.cell>
                                        <x-admin.badge :tone="$tp->waiting_count > 0 ? 'warning' : 'neutral'">{{ $tp->waiting_count }}</x-admin.badge>
                                    </x-admin.table.cell>
                                </x-admin.table.row>
                            @endforeach
                        </x-admin.table>
                    </x-admin.card>
                @endif
            </x-admin.tabs.panel>

            {{-- ==================== NOTIFIED ==================== --}}
            <x-admin.tabs.panel name="notified">
                <x-admin.card>
                    <x-admin.table>
                        <x-slot:head>
                            <x-admin.table.th>Customer</x-admin.table.th>
                            <x-admin.table.th>Email</x-admin.table.th>
                            <x-admin.table.th>Product</x-admin.table.th>
                            <x-admin.table.th>Variant</x-admin.table.th>
                            <x-admin.table.th>Requested</x-admin.table.th>
                            <x-admin.table.th>Notified</x-admin.table.th>
                        </x-slot:head>
                        @forelse ($notified as $item)
                            <x-admin.table.row>
                                <x-admin.table.cell class="text-v-text">{{ $item->name }}</x-admin.table.cell>
                                <x-admin.table.cell class="text-v-secondary">{{ $item->email }}</x-admin.table.cell>
                                <x-admin.table.cell class="text-v-text">{{ $item->product?->name ?? 'Deleted' }}</x-admin.table.cell>
                                <x-admin.table.cell class="text-v-secondary">{{ $item->variant?->name ?? '-' }}</x-admin.table.cell>
                                <x-admin.table.cell class="text-v-muted">{{ $item->created_at->format('M j, Y') }}</x-admin.table.cell>
                                <x-admin.table.cell class="text-v-accent">{{ $item->notified_at?->format('M j, Y g:i A') }}</x-admin.table.cell>
                            </x-admin.table.row>
                        @empty
                            <tr><td colspan="6"><x-admin.empty-state title="No notifications sent yet" /></td></tr>
                        @endforelse

                        <x-slot:footer>
                            @if ($notified->hasPages())
                                {{ $notified->links() }}
                            @endif
                        </x-slot:footer>
                    </x-admin.table>
                </x-admin.card>
            </x-admin.tabs.panel>

            {{-- ==================== EMAIL TEMPLATE ==================== --}}
            <x-admin.tabs.panel name="template">
                <x-admin.card class="max-w-2xl">
                    <form method="POST" action="{{ route('admin.stock-notifications.update-template') }}" data-v-form class="space-y-4">
                        @csrf
                        <x-admin.field name="subject" label="Subject" required>
                            <x-admin.input name="subject" :value="$template?->subject ?? ''" placeholder="Good news! {product_name} is back in stock" />
                        </x-admin.field>
                        <x-admin.field name="body" label="Body" required>
                            <textarea name="body" id="stock-template-body" rows="12"
                                class="tinymce-editor v-control w-full rounded-v-md border bg-v-surface px-3 py-2 text-[13px] text-v-text">{{ old('body', $template?->body ?? '') }}</textarea>
                        </x-admin.field>
                        <div class="rounded-v-lg bg-v-sunken p-4">
                            <p class="v-label mb-2">Placeholders</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach (['{customer_name}', '{product_name}', '{product_url}', '{variant_name}', '{product_image}', '{store_name}'] as $ph)
                                    <code class="v-num rounded-v-sm border border-v-border bg-v-surface px-2 py-1 text-xs text-v-accent">{{ $ph }}</code>
                                @endforeach
                            </div>
                        </div>
                        <x-admin.form-actions class="-mx-6">
                            <x-admin.button type="submit">Save Template</x-admin.button>
                        </x-admin.form-actions>
                    </form>
                </x-admin.card>

                <x-admin.card title="Test Email" class="mt-6 max-w-2xl">
                    <form method="POST" action="{{ route('admin.stock-notifications.send-test') }}" data-v-form class="flex items-center gap-3">
                        @csrf
                        <x-admin.button variant="secondary" type="submit">Send a test to {{ auth()->user()->email }}</x-admin.button>
                    </form>
                </x-admin.card>
            </x-admin.tabs.panel>
        </x-admin.tabs>

        @push('scripts')
        <script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
        <script>
            tinymce.init({
                selector: '.tinymce-editor',
                height: 350,
                menubar: false,
                plugins: 'lists link autolink code table',
                toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link table | removeformat code',
                content_style: 'body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 14px; }',
                branding: false,
                promotion: false,
                license_key: 'gpl',
                convert_urls: false,
            });
        </script>
        @endpush
</x-admin.settings-shell>
