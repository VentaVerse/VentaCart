<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\ExtensionManager;
use App\Services\Shipping\ShippingQuoteService;
use App\Support\ExtensionCard;
use Illuminate\Support\Str;

class ShippingSettingController extends Controller
{
    // Legacy '?tab=<slug>' deep links into the old single-page layout — each
    // now has its own dedicated provider page (see routes/admin/settings.php).
    private const TAB_REDIRECTS = [
        'free-shipping' => 'admin.shipping-settings.free-shipping',
        'weight-based' => 'admin.shipping-settings.weight-based',
        'flat-rate' => 'admin.shipping-settings.flat-rate',
        'dhl' => 'admin.shipping-settings.dhl',
    ];

    /**
     * Provider list — the chain in real evaluation order (from
     * ShippingQuoteService::getProviders()), with a one-line summary per
     * provider and a link to its own configuration page. The Origin card
     * and the Rate Tester stay here: they're chain-wide, not per-provider.
     */
    public function index()
    {
        if (is_string($tab = request()->query('tab')) && isset(self::TAB_REDIRECTS[$tab])) {
            return redirect()->route(self::TAB_REDIRECTS[$tab], [], 301);
        }

        // The chain, extension-driven (wave 2): every shipping-category
        // extension is a row with the standard button set, ordered by its
        // manifest chain_sequence (the quote chain's real evaluation
        // order; Fulfillment sits last as the courier hub). An enabled
        // extension's registered provider contributes its one-line
        // summary; anything else shows its manifest description only.
        $manager = app(ExtensionManager::class);
        $providersByKey = collect(app(ShippingQuoteService::class)->getProviders())
            ->keyBy(fn ($p) => $p->getKey());

        $chain = $manager->discovered()
            ->filter(fn (array $m) => ($m['type'] ?? '') === 'shipping')
            ->sortBy(fn (array $m) => $m['chain_sequence'] ?? 100)
            ->values()
            ->map(function (array $manifest) use ($manager, $providersByKey) {
                $card = ExtensionCard::for($manifest, $manager);
                $provider = $providersByKey->get($manifest['quote_key'] ?? '');
                $card['summary'] = ($provider && method_exists($provider, 'adminSummary'))
                    ? $provider->adminSummary()
                    : null;
                $card['chain_sequence'] = $manifest['chain_sequence'] ?? 100;

                return $card;
            });

        // The storefront cannot quote without at least one enabled rate
        // method (couriers are booking, not checkout rates — and
        // Fulfillment joins the list only after this is computed).
        $noMethodEnabled = $chain->every(fn (array $c) => $c['state'] !== 'enabled');

        $countries = Country::orderBy('name')->get();
        $defaultCountryId = Country::where('iso2', 'PH')->value('id') ?? $countries->first()?->id ?? 1;

        // Fulfillment (the courier hub) joins the same list as its own
        // standard row (owner ruling: every listing, every row, one
        // grammar), summarized by its enabled-courier count.
        $fulfillmentManifest = $manager->discovered()->get('courier-fulfillment');
        if ($fulfillmentManifest) {
            $card = ExtensionCard::for($fulfillmentManifest, $manager);
            if (class_exists(\Extensions\CourierFulfillment\CourierManager::class) && $card['state'] === 'enabled') {
                $enabledCouriers = count(\Extensions\CourierFulfillment\CourierManager::flagged());
                $card['summary'] = $enabledCouriers . ' ' . Str::plural('courier', $enabledCouriers) . ' enabled';
            } else {
                $card['summary'] = null;
            }
            $card['chain_sequence'] = 90;
            $chain = $chain->push($card);
        }

        $customerGroups = \App\Models\CustomerGroup::orderBy('name')->get(['id', 'name']);
        $addressProviders = app(\App\Services\AddressProviderRegistry::class)->toFrontendManifest();

        return view('admin.settings.shipping.index', compact(
            'chain', 'countries', 'defaultCountryId', 'noMethodEnabled',
            'customerGroups', 'addressProviders'
        ));
    }

}
