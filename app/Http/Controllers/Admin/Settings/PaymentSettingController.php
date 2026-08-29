<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Services\ExtensionManager;
use App\Support\ExtensionCard;

class PaymentSettingController extends Controller
{
    /**
     * The payment methods page, extension-driven (wave 2): every
     * payment-category extension is a row with the standard button set
     * (owner ruling 2026-08-18). Restrictions live in each method page's
     * Availability tab, reached through Configure.
     */
    public function index()
    {
        $manager = app(ExtensionManager::class);

        $cards = $manager->discovered()
            ->filter(fn (array $m) => ($m['type'] ?? '') === 'payment')
            ->sortBy(fn (array $m) => $m['title'] ?? $m['name'])
            ->values()
            ->map(fn (array $manifest) => ExtensionCard::for($manifest, $manager));

        // The storefront cannot check out without at least one enabled
        // payment method.
        $noMethodEnabled = $cards->every(fn (array $c) => $c['state'] !== 'enabled');

        // An enabled method with no "Order status on checkout" is not offered
        // at checkout (no fallback, owner ruling 2026-08-30). Name those
        // cards, keyed the way manifests name them: payment-<base-key>.
        $needsStatus = collect(\App\Services\PaymentManager::enabledMethods())
            ->filter(fn ($m) => \App\Services\PaymentManager::checkoutStatus($m) === null)
            ->map(fn ($m) => 'payment-' . str_replace('_', '-', $m->getBaseKey()))
            ->values()
            ->all();

        return view('admin.settings.payments.index', [
            'cards' => $cards,
            'noMethodEnabled' => $noMethodEnabled,
            'needsStatus' => $needsStatus,
        ]);
    }
}
