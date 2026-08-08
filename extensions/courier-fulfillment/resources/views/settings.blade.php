<x-admin.settings-shell title="Courier Settings" :wide="true" active="fulfillment">

    <div class="space-y-6">

        {{-- Module-level card: not owned by any one courier's schema. --}}
        <x-admin.card title="Fulfillment" description="Pause the manual booking queue, choose which payment methods book as COD, and share your webhook URLs with couriers.">
            <form method="POST" action="{{ route('admin.fulfillment.settings.update') }}" data-v-form class="space-y-6">
                @csrf

                <x-admin.field name="settings[fulfillment_enabled]"
                    help="On by default: installing and enabling this extension is already the signal you want it active. Turn off to pause the booking queue (Sales › Fulfillment shows an explanatory page instead) without disabling the extension itself. Bookings triggered by the ERP API keep working either way.">
                    <label class="flex items-center gap-3 text-[13px] text-v-text cursor-pointer">
                        <x-admin.toggle name="settings[fulfillment_enabled]" :checked="($settings['fulfillment_enabled'] ?? '1') !== '0'" />
                        Fulfillment queue active
                    </label>
                </x-admin.field>

                {{-- fulfillment-tabs-v3 Task 4: the core status a queue
                     "Cancel order" action sets — applied through the same
                     history + stock path the order page uses. --}}
                <x-admin.field name="settings[cancel_order_status_id]" label="Cancel order status"
                    help="The order status a Cancel order action in the fulfillment queue applies. History and stock behave exactly as if the status were changed from the order page (customer is not notified).">
                    <x-admin.select name="settings[cancel_order_status_id]">
                        {{-- Fix-round minor: an unset value keeps this
                             placeholder selected so saving the hub never
                             silently persists a status the admin did not
                             choose. --}}
                        <option value="" @selected($cancelOrderStatusId === null)>Use default (Canceled)</option>
                        @foreach ($orderStatuses as $id => $name)
                            <option value="{{ $id }}" @selected((string) $id === (string) $cancelOrderStatusId)>{{ $name }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>

                {{-- fulfillment-placement Task 3: the two statuses that carry
                     an order across the To Pack / To Handover line. Booking is
                     the store's own action, so nothing but this move can take
                     a booked order out of To Pack. --}}
                <x-admin.field name="settings[booked_order_status_id]" label="Booked order status"
                    help="Applied automatically when a booking succeeds. This is what moves the order from To Pack to To Handover, so the queue offers its waybill and its cancel next. Only statuses placed under To Handover can be chosen. History and stock behave exactly as if the status were changed from the order page (customer is not notified).">
                    <x-admin.select name="settings[booked_order_status_id]">
                        <option value="" @selected($bookedOrderStatusId === null)>
                            {{ $bookedDefaultName ? 'Use default (' . $bookedDefaultName . ')' : 'No status is placed under To Handover' }}
                        </option>
                        @foreach ($handoverStatuses as $id => $name)
                            <option value="{{ $id }}" @selected((string) $id === (string) $bookedOrderStatusId)>{{ $name }}</option>
                        @endforeach
                    </x-admin.select>
                    @if (empty($handoverStatuses))
                        <p class="text-xs text-v-danger mt-2">Booking is refused until at least one order status is placed under To Handover. Place one under Sales, Order Statuses.</p>
                    @endif
                </x-admin.field>

                <x-admin.field name="settings[unbooked_order_status_id]" label="Unbooked order status"
                    help="Applied when a courier booking is cancelled, returning the order to To Pack so it can be booked again. The status the order held before it was booked wins when it is still placed under To Pack; this is the fallback. Only statuses placed under To Pack can be chosen.">
                    <x-admin.select name="settings[unbooked_order_status_id]">
                        <option value="" @selected($unbookedOrderStatusId === null)>
                            {{ $unbookedDefaultName ? 'Use default (' . $unbookedDefaultName . ')' : 'No status is placed under To Pack' }}
                        </option>
                        @foreach ($packStatuses as $id => $name)
                            <option value="{{ $id }}" @selected((string) $id === (string) $unbookedOrderStatusId)>{{ $name }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>

                <div>
                    <h3 class="text-[13px] font-semibold text-v-text mb-1">COD payment methods</h3>
                    <p class="text-xs text-v-muted mb-3">Orders paid with a checked method are booked as COD (the rider collects the order total). Everything else books as prepaid.</p>
                    <input type="hidden" name="settings[cod_payment_methods][]" value="__none__">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2">
                        @foreach ($paymentMethodOptions as $slug => $method)
                            <label class="flex items-center gap-2 text-[13px] text-v-text cursor-pointer">
                                <input type="checkbox" name="settings[cod_payment_methods][]" value="{{ $slug }}"
                                    {{ in_array($slug, $codMethods, true) ? 'checked' : '' }}
                                    class="w-4 h-4 rounded-v-sm border-v-strong text-v-accent focus:ring-v-accent bg-v-surface">
                                <span class="text-xs text-v-secondary">{{ $method['label'] }}</span>
                                @if ($method['legacy'])
                                    <x-admin.badge tone="neutral">legacy</x-admin.badge>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                <x-admin.form-actions>
                    <x-admin.button type="submit">Save</x-admin.button>
                </x-admin.form-actions>
            </form>
        </x-admin.card>

        {{-- Webhook URLs: module-level plumbing (the tokens are
             server-generated, never editable), one per registered courier. --}}
        <x-admin.card title="Webhook URLs" description="Couriers have no UI to set these. Email/Slack each courier its URL so they push status updates here. Unique to this store.">
            <div class="space-y-4">
                @foreach ($courierCards as $key => $card)
                    <div x-data="{ copied: false }">
                        <p class="text-[13px] font-semibold text-v-text mb-1.5">{{ $card['courier']->getName() }}</p>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="{{ $card['webhookUrl'] }}" x-ref="hook"
                                class="v-control flex-1 h-[var(--control-h)] rounded-v-md border border-v-border bg-v-sunken px-3 text-xs font-mono text-v-secondary">
                            <x-admin.button type="button" variant="secondary" size="sm"
                                x-on:click="navigator.clipboard.writeText($refs.hook.value); copied=true; setTimeout(()=>copied=false,1500)">
                                <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied &check;</span>
                            </x-admin.button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-admin.card>

        {{-- One card per registered courier — name, enabled state, mode chip,
             credential-completeness hint — linking to that courier's own
             settings page (schema fields, actions, status mapping all live
             there now). All derived from CourierFulfillmentInterface, so a
             new courier gets its card and page for free. --}}
        @foreach ($courierCards as $key => $card)
            <x-admin.card :title="$card['courier']->getName()">
                <x-slot:headerActions>
                    <x-admin.button variant="secondary" size="sm" href="{{ $card['url'] }}">Configure</x-admin.button>
                </x-slot:headerActions>
                <div class="flex flex-wrap items-center gap-2">
                    <x-admin.badge :tone="$card['enabled'] ? 'success' : 'neutral'">
                        {{ $card['enabled'] ? 'Enabled' : 'Disabled' }}
                    </x-admin.badge>
                    @if ($card['modeLabel'])
                        <x-admin.badge tone="warning">{{ $card['modeLabel'] }}</x-admin.badge>
                    @endif
                    @if ($card['credentialHint'])
                        <span class="text-xs {{ str_contains($card['credentialHint'], 'missing') ? 'text-v-warning' : 'text-v-muted' }}">
                            {{ $card['credentialHint'] }}
                        </span>
                    @endif
                </div>
            </x-admin.card>
        @endforeach

    </div>

</x-admin.settings-shell>
