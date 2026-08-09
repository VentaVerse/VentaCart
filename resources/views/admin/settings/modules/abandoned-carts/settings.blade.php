<x-admin.settings-shell title="Abandoned Cart Settings" :wide="true">
        @include('admin.settings.modules.abandoned-carts.partials.nav')

        <div class="max-w-2xl space-y-6">
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
                <x-admin.card title="Scheduling">
                    <div class="flex items-center gap-2">
                        <x-admin.button variant="secondary" size="sm" :href="route('admin.settings.section', 'automations')">
                            Open Automations
                        </x-admin.button>
                        <x-admin.hint label="About the cart:notify schedule">Notifications fire through the Venta scheduler. If your crontab still runs cart:notify directly, remove that line or every cart is notified twice.</x-admin.hint>
                    </div>
                </x-admin.card>
        </div>
</x-admin.settings-shell>
