<x-admin.settings-shell title="Courier Settings" :wide="true" active="fulfillment">
    @push('head')
        @vite('extensions/courier-fulfillment/resources/css/fulfillment.css')
    @endpush

    <div class="space-y-6">

        {{-- Setup notice (owner ruling, 2026-08-09). The module places no
             status on its own, so a fresh install has nowhere to move a booked
             order to and booking refuses outright
             (FulfillmentService::book()'s no_booked_status branch). This is a
             STATE, not an instruction: it names what is missing and links to
             the page that fixes it, and disappears the moment any status is
             placed under To Handover. --}}
        @if (empty($handoverStatuses))
            <x-admin.empty-state variant="notice" role="status"
                title="No order status is placed under To Handover, so booking is refused">
                <a class="v-notice__btn" href="{{ route('admin.fulfillment.settings.placement') }}">Status placement</a>
            </x-admin.empty-state>
        @endif

        {{-- Status placement lives on its own page: every order status, the
             queue tab it belongs to, and the live order count behind each. --}}
        <x-admin.card title="Status placement">
            <x-slot:headerActions>
                <x-admin.button variant="secondary" size="sm" href="{{ route('admin.fulfillment.settings.placement') }}">Configure</x-admin.button>
            </x-slot:headerActions>
            <div class="flex flex-wrap items-center gap-2">
                <x-admin.badge :tone="$unplacedStatusCount > 0 ? 'warning' : 'success'">
                    {{ $placedStatusCount }} placed
                </x-admin.badge>
                @if ($unplacedStatusCount > 0)
                    <span class="text-xs text-v-warning">{{ $unplacedStatusCount }} not placed, All tab only</span>
                @endif
            </div>
        </x-admin.card>

        {{-- Module-level card: not owned by any one courier's schema. --}}
        <x-admin.card title="Fulfillment">
            <form method="POST" action="{{ route('admin.fulfillment.settings.update') }}" data-v-form class="space-y-6">
                @csrf

                <x-admin.field name="settings[fulfillment_enabled]"
                    help="Turning this off hides Fulfillment from the Sales menu and pauses the queue. Bookings triggered by the ERP API keep working, and the extension stays installed.">
                    <x-admin.toggle name="settings[fulfillment_enabled]" :checked="($settings['fulfillment_enabled'] ?? '1') !== '0'" label="Fulfillment queue active, shown in the Sales menu" />
                </x-admin.field>

                {{-- fulfillment-tabs-v3 Task 4: the core status a queue
                     "Cancel order" action sets — applied through the same
                     history + stock path the order page uses. --}}
                <x-admin.field name="settings[cancel_order_status_id]" label="Cancel order status"
                    help="Applied by a Cancel order action in the queue. History and stock behave as if the status were changed from the order page, and the customer is not notified.">
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
                    help="Applied when a booking succeeds, moving the order from To Pack to To Handover. History and stock behave as if the status were changed from the order page, and the customer is not notified.">
                    <x-admin.select name="settings[booked_order_status_id]">
                        <option value="" @selected($bookedOrderStatusId === null)>
                            {{ $bookedDefaultName ? 'Use default (' . $bookedDefaultName . ')' : 'No status is placed under To Handover' }}
                        </option>
                        @foreach ($handoverStatuses as $id => $name)
                            <option value="{{ $id }}" @selected((string) $id === (string) $bookedOrderStatusId)>{{ $name }}</option>
                        @endforeach
                    </x-admin.select>
                </x-admin.field>

                <x-admin.field name="settings[unbooked_order_status_id]" label="Unbooked order status"
                    help="The fallback applied when a courier booking is cancelled. The status the order held before it was booked wins whenever it is still placed under To Pack.">
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
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="v-subhead">COD payment methods</h3>
                        <x-admin.hint label="About COD payment methods">An order paid with a checked method is booked as COD, so the rider collects the order total. Everything else books as prepaid.</x-admin.hint>
                    </div>
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
        <x-admin.card title="Webhook URLs">
            <x-slot name="headerActions">
                <x-admin.hint label="About these URLs" class="v-hint--end">Each URL is unique to this store. A courier given the wrong one cannot push its status updates.</x-admin.hint>
            </x-slot>
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
