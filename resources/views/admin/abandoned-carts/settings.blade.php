<x-admin.settings-shell active="abandoned-carts" title="Abandoned Cart Settings" :wide="true">
        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            @include('admin.abandoned-carts.partials.nav')

            <div class="min-w-0 max-w-2xl flex-1 space-y-6">
                {{-- Enable/Disable --}}
                <form method="POST" action="{{ route('admin.abandoned-carts.settings.update') }}" data-v-form>
                    @csrf
                    <x-admin.card title="Abandoned Cart Notifications"
                        description="When enabled, the cron command will process rules and send notification emails to customers with abandoned carts.">
                        <div class="space-y-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <x-admin.toggle name="enabled" :checked="$enabled" />
                                <span class="text-[13px] font-medium text-v-text">Enable abandoned cart notifications</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <x-admin.toggle name="skip_out_of_stock" :checked="$skipOutOfStock" />
                                <span class="text-[13px] text-v-text">Skip notifications when all cart items are out of stock</span>
                            </label>
                        </div>
                        <x-slot name="footer">
                            <div class="flex justify-end">
                                <x-admin.button type="submit">Save</x-admin.button>
                            </div>
                        </x-slot>
                    </x-admin.card>
                </form>

                {{-- Scheduling --}}
                <x-admin.card title="Scheduling"
                    description="Abandoned cart notifications run automatically via the Venta scheduler. Change how often they fire — or run them manually — in the Automations panel.">
                    <x-admin.button variant="secondary" size="sm" :href="route('admin.settings.section', 'automations')">
                        Manage in Settings → Automations
                    </x-admin.button>

                    <div class="mt-4 rounded-v-md border border-v-border bg-[color-mix(in_srgb,var(--warning)_10%,transparent)] p-3 text-xs text-v-warning">
                        <strong>Upgrading from an older Venta install?</strong> If your server's crontab still has a line running <code class="rounded bg-v-sunken px-1">cart:notify</code> directly, remove it — the command now fires through the universal scheduler and the old line will cause duplicate runs.
                    </div>
                </x-admin.card>
            </div>
        </div>
</x-admin.settings-shell>
