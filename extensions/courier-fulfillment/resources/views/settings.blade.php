<x-admin3.shell title="Courier Settings">
    @push('head')
        @vite('extensions/courier-fulfillment/resources/css/fulfillment.css')
    @endpush

    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Fulfillment</span>
            </div>
            <h1 class="v3-head__title">Fulfillment</h1>
        </div>
        <div class="v3-head__actions">
            <x-admin3.extension-switch name="courier-fulfillment" />
        </div>
    </div>

    @include('courier-fulfillment::partials.config-tabs')

    <div class="v3-settle">

        {{-- Setup notice (owner ruling, 2026-08-09). The module places no
             status on its own, so a fresh install has nowhere to move a booked
             order to and booking refuses outright
             (FulfillmentService::book()'s no_booked_status branch). This is a
             STATE, not an instruction: it names what is missing and links to
             the page that fixes it, and disappears the moment any status is
             placed under To Handover. --}}
        @if (empty($handoverStatuses))
            <x-admin3.panel title="Booking is refused" role="status">
                <x-slot:acts>
                    <a href="{{ route('admin.fulfillment.settings.placement') }}" class="v3-btn v3-btn--primary v3-btn--sm">Status Mapping</a>
                </x-slot:acts>
                <span class="v3-badge v3-badge--warn">No order status is placed under To Handover</span>
            </x-admin3.panel>
        @endif

        {{-- Module-level settings: not owned by any one courier's schema. --}}
        <x-admin3.form :action="route('admin.fulfillment.settings.update')" id="fulfillment-settings-form">
            <x-admin3.panel title="Fulfillment">
                <label class="v3-toggle">
                    <input type="hidden" name="settings[fulfillment_enabled]" value="0">
                    <input type="checkbox" name="settings[fulfillment_enabled]" value="1" @checked(($settings['fulfillment_enabled'] ?? '1') !== '0')>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    Fulfillment queue active, shown in the Sales menu
                    <x-admin3.help text="Turning this off hides Fulfillment from the Sales menu and pauses the queue. Bookings triggered by the ERP API keep working, and the extension stays installed." />
                </label>

                {{-- fulfillment-tabs-v3 Task 4: the core status a queue
                     "Cancel order" action sets, applied through the same
                     history + stock path the order page uses. --}}
                <div class="v3-gap">
                    <x-admin3.field name="settings.cancel_order_status_id" label="Cancel order status"
                        hint="Applied by a Cancel order action in the queue. History and stock behave as if the status were changed from the order page, and the customer is not notified.">
                        <select name="settings[cancel_order_status_id]" class="v3-control">
                            <option value="" @selected($cancelOrderStatusId === null)>Use default (Canceled)</option>
                            @foreach ($orderStatuses as $id => $name)
                                <option value="{{ $id }}" @selected((string) $id === (string) $cancelOrderStatusId)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </x-admin3.field>
                </div>

                {{-- fulfillment-placement Task 3: the two statuses that carry
                     an order across the To Pack / To Handover line. --}}
                <div class="v3-gap">
                    <x-admin3.field name="settings.booked_order_status_id" label="Booked order status"
                        hint="Applied when a booking succeeds, moving the order from To Pack to To Handover. History and stock behave as if the status were changed from the order page, and the customer is not notified.">
                        <select name="settings[booked_order_status_id]" class="v3-control">
                            <option value="" @selected($bookedOrderStatusId === null)>
                                {{ $bookedDefaultName ? 'Use default (' . $bookedDefaultName . ')' : 'No status is placed under To Handover' }}
                            </option>
                            @foreach ($handoverStatuses as $id => $name)
                                <option value="{{ $id }}" @selected((string) $id === (string) $bookedOrderStatusId)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </x-admin3.field>
                </div>

                <div class="v3-gap">
                    <x-admin3.field name="settings.unbooked_order_status_id" label="Unbooked order status"
                        hint="The fallback applied when a courier booking is cancelled. The status the order held before it was booked wins whenever it is still placed under To Pack.">
                        <select name="settings[unbooked_order_status_id]" class="v3-control">
                            <option value="" @selected($unbookedOrderStatusId === null)>
                                {{ $unbookedDefaultName ? 'Use default (' . $unbookedDefaultName . ')' : 'No status is placed under To Pack' }}
                            </option>
                            @foreach ($packStatuses as $id => $name)
                                <option value="{{ $id }}" @selected((string) $id === (string) $unbookedOrderStatusId)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </x-admin3.field>
                </div>

                <div class="v3-gap">
                    <div class="v3-label v3-gap-sm">
                        COD payment methods
                        <x-admin3.help text="An order paid with a checked method is booked as COD, so the rider collects the order total. Everything else books as prepaid." />
                    </div>
                    <input type="hidden" name="settings[cod_payment_methods][]" value="__none__">
                    <div class="v3-grid v3-grid--3">
                        @foreach ($paymentMethodOptions as $slug => $method)
                            <label class="v3-check">
                                <input type="checkbox" name="settings[cod_payment_methods][]" value="{{ $slug }}"
                                    {{ in_array($slug, $codMethods, true) ? 'checked' : '' }}>
                                {{ $method['label'] }}
                                @if ($method['legacy'])
                                    <span class="v3-badge v3-badge--quiet">legacy</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>
            </x-admin3.panel>
        </x-admin3.form>

    </div>
</x-admin3.shell>
