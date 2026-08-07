<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethodRestriction;
use App\Models\Setting;
use App\Support\PaymentFamilies;

class PaymentSettingController extends Controller
{
    public function index()
    {
        $settings = [];
        foreach (Setting::where('group', 'payment')->get() as $s) {
            $settings[$s->key] = $s->value;
        }

        $restrictions = PaymentMethodRestriction::where('is_active', true)
            ->whereIn('payment_method', array_keys(PaymentFamilies::LIST))
            ->get()
            ->keyBy('payment_method');

        $cards = [];
        foreach (PaymentFamilies::LIST as $family => $meta) {
            $restriction = $restrictions->get($family);
            $displayName = $settings["{$family}_display_name"] ?? '';
            $cards[] = [
                'family' => $family,
                'name' => !empty($displayName) ? $displayName : $meta['default_name'],
                'description' => $meta['description'],
                'enabled' => ($settings["{$family}_enabled"] ?? '0') === '1',
                'restricted' => $restriction !== null,
                'restriction_summary' => $restriction?->getConditionsSummary(),
            ];
        }

        return view('admin.payment-settings.index', compact('cards'));
    }
}
