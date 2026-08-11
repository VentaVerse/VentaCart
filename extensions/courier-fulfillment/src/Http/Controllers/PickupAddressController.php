<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use Extensions\CourierFulfillment\Http\Requests\PickupAddressRequest;
use Extensions\CourierFulfillment\Models\PickupAddress;
use Extensions\CourierFulfillment\Support\PickupCountry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD for the places parcels leave from, mounted under the module's own
 * settings root (Settings > Modules > Courier Fulfillment > Pickup
 * addresses), same convention as the status-placement page beside it.
 *
 * The country is never taken from the request. It is resolved from
 * PickupCountry (iso2 lookup) on every write, so a forged country_id changes
 * nothing, and a missing/inactive country row fails loudly with one message
 * instead of writing a row no courier payload can be built from.
 *
 * Delete rules, stated once and enforced here:
 *
 *  - The LAST remaining address cannot be deleted. Both couriers build their
 *    sender block from an address record; with none on file every booking
 *    refuses, so the module never lets the operator reach that state by
 *    deleting. Editing the last address is always available instead.
 *  - Deleting the DEFAULT while others exist is allowed, and the oldest
 *    remaining address is promoted in the same transaction. Something must
 *    hold the flag, and "the one that has been here longest" needs no
 *    judgment from the operator mid-delete.
 */
class PickupAddressController extends Controller
{
    public function index()
    {
        Gate::authorize('courier-fulfillment.configure');

        return view('courier-fulfillment::pickup-addresses.index', [
            'addresses' => PickupAddress::with('country')
                ->orderByDesc('is_default')
                ->orderBy('label')
                ->get(),
            'countryMissing' => PickupCountry::tryResolve() === null,
            'countryMissingMessage' => PickupCountry::missingMessage(),
        ]);
    }

    public function create()
    {
        Gate::authorize('courier-fulfillment.configure');

        return $this->form(new PickupAddress());
    }

    public function edit(PickupAddress $pickupAddress)
    {
        Gate::authorize('courier-fulfillment.configure');

        return $this->form($pickupAddress);
    }

    public function store(PickupAddressRequest $request): RedirectResponse
    {
        try {
            $country = PickupCountry::resolve();
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $address = DB::transaction(function () use ($request, $country) {
            $address = new PickupAddress($this->attributes($request));
            $address->country_id = $country->id;
            // The model promotes the very first row on its own; anything
            // after that only becomes default when the form asked.
            $address->is_default = $request->boolean('is_default');
            $address->save();

            return $address;
        });

        return redirect()
            ->route('admin.fulfillment.pickup-addresses.index')
            ->with('success', "Pickup address \"{$address->label}\" added.");
    }

    public function update(PickupAddressRequest $request, PickupAddress $pickupAddress): RedirectResponse
    {
        try {
            $country = PickupCountry::resolve();
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        DB::transaction(function () use ($request, $pickupAddress, $country) {
            $pickupAddress->fill($this->attributes($request));
            $pickupAddress->country_id = $country->id;

            // Un-ticking the box on the row that currently holds the flag
            // would leave the store with no default at all, so the only way
            // out of being default is another address taking the flag.
            if ($request->boolean('is_default')) {
                $pickupAddress->is_default = true;
            }

            $pickupAddress->save();
        });

        return redirect()
            ->route('admin.fulfillment.pickup-addresses.index')
            ->with('success', "Pickup address \"{$pickupAddress->label}\" saved.");
    }

    public function makeDefault(PickupAddress $pickupAddress): RedirectResponse
    {
        Gate::authorize('courier-fulfillment.configure');

        $pickupAddress->makeDefault();

        return back()->with('success', "Parcels now leave from \"{$pickupAddress->label}\" by default.");
    }

    public function destroy(PickupAddress $pickupAddress): RedirectResponse
    {
        Gate::authorize('courier-fulfillment.configure');

        if (PickupAddress::query()->count() <= 1) {
            return back()->with('error', 'This is the only pickup address on file, and a booking cannot be built without one. Edit it instead.');
        }

        DB::transaction(function () use ($pickupAddress) {
            $wasDefault = $pickupAddress->is_default;
            $label = $pickupAddress->label;
            $pickupAddress->delete();

            if ($wasDefault) {
                PickupAddress::query()->orderBy('id')->first()?->makeDefault();
            }

            session()->flash('success', "Pickup address \"{$label}\" deleted.");
        });

        return back();
    }

    /** Everything the form is allowed to write. The country is not in it. */
    private function attributes(PickupAddressRequest $request): array
    {
        return $request->safe()->only([
            'label',
            'contact_name',
            'contact_phone',
            'address_1',
            'barangay',
            'district',
            'city',
            'state',
            'postcode',
        ]);
    }

    /**
     * The cascade the form drives, shaped exactly like
     * App\Services\Address\DeepAddressProvider::fields() (same keys, same
     * paramFrom/paramName contract, same conditional flag) so the admin form
     * can run the storefront's own cascade code unchanged. paramFrom may
     * name two levels ("city|district") because a PH barangay hangs off a
     * district when one exists and off the city otherwise; the finer parent
     * wins, which is why the JS walks that list in reverse.
     *
     * Only the endpoint differs from DeepAddressProvider::fields(): that one
     * points at the public storefront route, this one at the admin's
     * authenticated twin. Both serve DeepAddressData.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cascadeLevels(): array
    {
        $levels = [];

        foreach (config('localization.deep_address.' . PickupCountry::ISO . '.levels', []) as $level) {
            $levels[] = [
                'key'         => $level['key'],
                'label'       => $level['label'],
                'required'    => (bool) ($level['required'] ?? false),
                'endpoint'    => route('admin.address.deep', [
                    'country' => PickupCountry::ISO,
                    'level'   => $level['key'],
                ]),
                'paramFrom'   => $level['parent'] ?? null,
                'paramName'   => empty($level['parent']) ? null : 'parent',
                'conditional' => (bool) ($level['conditional'] ?? false),
            ];
        }

        return $levels;
    }

    private function form(PickupAddress $address)
    {
        $country = PickupCountry::tryResolve();

        return view('courier-fulfillment::pickup-addresses.form', [
            'address'       => $address,
            'country'       => $country,
            'countryMissingMessage' => PickupCountry::missingMessage(),
            // When this row is (or will be) the only one on file, the default
            // flag is not a choice: the model holds it there regardless, so
            // the form states it instead of rendering a control that cannot
            // change anything.
            'defaultLocked' => PickupAddress::query()->count() <= ($address->exists ? 1 : 0),
            // The cascade's own vocabulary, read from the same config the
            // storefront checkout reads. Nothing about PH is spelled out in
            // the view: level keys, labels, which levels are optional and
            // which hang off which parent all arrive from here. Only the
            // endpoint differs from DeepAddressProvider::fields(), which
            // points at the public storefront route; the admin gets its own
            // authenticated one, serving the same data from the same service.
            'levels'        => $this->cascadeLevels(),
        ]);
    }
}
