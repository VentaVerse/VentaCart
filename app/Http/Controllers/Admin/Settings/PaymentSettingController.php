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

        $noMethodEnabled = collect($cards)->every(fn (array $c) => ! $c['enabled']);

        return view('admin.settings.payments.index', compact('cards', 'noMethodEnabled'));
    }

    public function enable(string $family)
    {
        return $this->setEnabled($family, true);
    }

    public function disable(string $family)
    {
        return $this->setEnabled($family, false);
    }

    /**
     * The standard row switch (owner ruling 2026-08-18: one button
     * grammar on every listing — no anonymous toggles). {family}
     * validates against PaymentFamilies::LIST so nothing else reaches
     * a write.
     */
    private function setEnabled(string $family, bool $on)
    {
        abort_unless(array_key_exists($family, PaymentFamilies::LIST), 404);

        Setting::updateOrCreate(
            ['group' => 'payment', 'key' => "{$family}_enabled"],
            ['value' => $on ? '1' : '0', 'type' => 'text'],
        );

        app(\App\Services\ThemeService::class)->clearCache();

        $name = PaymentFamilies::LIST[$family]['default_name'];

        return redirect()->route('admin.payment-settings.index')
            ->with('success', $name . ($on ? ' enabled.' : ' disabled.'));
    }
}
