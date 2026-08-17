<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use App\Models\PaymentMethodRestriction;
use App\Models\Setting;
use App\Support\PaymentFamilies;
use App\Support\PaymentRestrictionFormData;

/**
 * The method page FRAMEWORK (core): tabs, display-name setting, and the
 * Availability (restrictions) tab. The Configuration tab's content is the
 * method extension's own view ("payment-{name}::config"); anything that
 * view needs beyond this page's shared data (bank accounts, masked API
 * keys) arrives via a view composer the extension registers. A family
 * whose extension is not enabled 404s: its page does not exist, exactly
 * like any other disabled extension's pages.
 */
class PaymentMethodPageController extends Controller
{
    public function show(string $method)
    {
        $families = PaymentFamilies::enabledFamilies();
        abort_unless(array_key_exists($method, $families), 404);

        $meta = $families[$method];

        $settings = ['payment' => []];
        foreach (Setting::where('group', 'payment')->get() as $s) {
            $settings['payment'][$s->key] = $s->value;
        }

        $displayName = $settings['payment']["{$method}_display_name"] ?? '';
        $name = !empty($displayName) ? $displayName : $meta['default_name'];

        $orderStatuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $restriction = PaymentMethodRestriction::where('payment_method', $method)->first();

        return view('admin.settings.payments.show', [
            'method' => $method,
            'configView' => $meta['extension'] . '::config',
            'name' => $name,
            'defaultName' => $meta['default_name'],
            'description' => $meta['description'],
            'settings' => $settings,
            'orderStatuses' => $orderStatuses,
            'restriction' => $restriction,
            ...PaymentRestrictionFormData::get(),
        ]);
    }
}
