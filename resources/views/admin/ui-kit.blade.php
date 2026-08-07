<x-admin.shell title="UI Kit">
    <x-admin.page title="UI Kit">
        <div class="space-y-8">
            <x-admin.card title="Buttons" description="All variants and sizes. Toggle themes with the topbar switch.">
                <div class="flex flex-wrap items-center gap-3">
                    <x-admin.button variant="primary">Primary</x-admin.button>
                    <x-admin.button variant="secondary">Secondary</x-admin.button>
                    <x-admin.button variant="danger">Danger</x-admin.button>
                    <x-admin.button variant="ghost">Ghost</x-admin.button>
                    <x-admin.button variant="primary" size="sm">Small</x-admin.button>
                    <x-admin.button variant="primary" data-loading="true">Loading</x-admin.button>
                    <x-admin.button href="#">Link button</x-admin.button>
                </div>
            </x-admin.card>

            <x-admin.card title="Badges">
                <div class="flex flex-wrap gap-2">
                    <x-admin.badge tone="neutral">Draft</x-admin.badge>
                    <x-admin.badge tone="success">Complete</x-admin.badge>
                    <x-admin.badge tone="warning">Processing</x-admin.badge>
                    <x-admin.badge tone="danger">Failed</x-admin.badge>
                    <x-admin.badge tone="info">Shipped</x-admin.badge>
                </div>
            </x-admin.card>

            <x-admin.card title="Forms" description="Field wrapper, controls, error states.">
                <form data-v-form class="max-w-xl space-y-5" onsubmit="return false;">
                    <x-admin.field name="demo_name" label="Store name" help="Shown in the storefront header.">
                        <x-admin.input name="demo_name" value="NewGearDay" />
                    </x-admin.field>
                    <x-admin.field name="demo_country" label="Country">
                        <x-admin.select name="demo_country">
                            <option>Philippines</option>
                            <option>Singapore</option>
                        </x-admin.select>
                    </x-admin.field>
                    <x-admin.field name="demo_notes" label="Notes">
                        <x-admin.textarea name="demo_notes" rows="3" />
                    </x-admin.field>
                    <div class="flex items-center gap-6">
                        <x-admin.toggle name="demo_active" :checked="true" />
                        <x-admin.checkbox name="demo_agree" label="Send review emails" :checked="true" />
                    </div>
                    <x-admin.radio-group name="demo_mode" :options="['sale' => 'Sale price (strikethrough)', 'override' => 'Override (clean replacement)']" value="sale" />
                    <x-admin.form-actions>
                        <x-admin.button variant="secondary">Cancel</x-admin.button>
                        <x-admin.button variant="primary" type="submit">Save changes</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>

            <x-admin.card title="Cards & stats">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <x-admin.stat label="Today" value="₱48,230" />
                    <x-admin.stat label="Pending" value="14" tone="warning" />
                    <x-admin.stat label="Failed syncs" value="2" tone="danger" />
                </div>
            </x-admin.card>

            <x-admin.card title="Table">
                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th sortable field="customer">Customer</x-admin.table.th>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th align="right">Total</x-admin.table.th>
                    </x-slot:head>
                    <x-admin.table.row>
                        <x-admin.table.cell>Miguel Santos</x-admin.table.cell>
                        <x-admin.table.cell><x-admin.badge tone="warning">Processing</x-admin.badge></x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>₱2,450.00</x-admin.table.cell>
                    </x-admin.table.row>
                    <x-admin.table.row>
                        <x-admin.table.cell>Ana Reyes</x-admin.table.cell>
                        <x-admin.table.cell><x-admin.badge tone="info">Shipped</x-admin.badge></x-admin.table.cell>
                        <x-admin.table.cell align="right" mono>₱899.00</x-admin.table.cell>
                    </x-admin.table.row>
                    <x-slot:footer><span class="text-xs text-v-muted">Showing 2 of 2</span></x-slot:footer>
                </x-admin.table>
            </x-admin.card>

            <x-admin.card title="Feedback">
                <div class="flex flex-wrap gap-3" x-data>
                    <x-admin.button variant="secondary" @click="$store.toasts.push('Saved successfully.', 'success')">Success toast</x-admin.button>
                    <x-admin.button variant="secondary" @click="$store.toasts.push('Something went wrong.', 'danger')">Error toast</x-admin.button>
                    <x-admin.button variant="secondary" @click="$dispatch('open-modal', 'demo-modal')">Open modal</x-admin.button>
                    <x-admin.button variant="danger" @click="$dispatch('open-modal', 'demo-confirm')">Delete something</x-admin.button>
                </div>
                <x-admin.modal name="demo-modal" title="Demo modal">
                    <p class="text-[13px] text-v-secondary">Modal body content. Press Escape or click outside to close.</p>
                </x-admin.modal>
                <x-admin.confirm-dialog name="demo-confirm" title="Delete this item?" message="This action cannot be undone." confirm="Delete" action="#" method="POST" />
            </x-admin.card>

            <x-admin.card title="Empty states">
                <x-admin.empty-state icon="products" title="No products yet" description="Products added here appear in your storefront immediately.">
                    <x-admin.button variant="primary"><x-admin.icon name="plus" class="w-3.5 h-3.5" /> Add product</x-admin.button>
                </x-admin.empty-state>
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>
