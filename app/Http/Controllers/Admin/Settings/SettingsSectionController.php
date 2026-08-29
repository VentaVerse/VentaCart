<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Currency;
use App\Models\Setting;
use App\Support\SettingsNav;
use Illuminate\Support\Facades\Crypt;

class SettingsSectionController extends Controller
{
    /** section key => settings groups to load */
    private const SECTION_GROUPS = [
        'general' => ['general', 'identity'],
        'mail' => ['mail'],
        'checkout' => ['checkout', 'stock'],
        'currencies' => [],
        'security' => ['security'],
        'automations' => [],
    ];

    public function show(string $section)
    {
        $item = SettingsNav::find($section);
        abort_if($item === null || $item['section'] === null || ! isset(self::SECTION_GROUPS[$section]), 404);

        $settings = [];
        foreach (self::SECTION_GROUPS[$section] as $group) {
            $settings[$group] = Setting::where('group', $group)->pluck('value', 'key')->all();
        }

        $data = ['item' => $item, 'settings' => $settings];

        if ($section === 'currencies') {
            $data['currencies'] = Currency::orderBy('is_default', 'desc')->orderBy('code')->get();
        }
        if ($section === 'checkout') {
            // For "Cancelled by customer lands on".
            $data['orderStatuses'] = \App\Models\OrderStatus::where('store_id', (int) config('app.store_id', 1))
                ->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
        }
        if ($section === 'automations') {
            $data['automations'] = Automation::orderBy('id')->get();
        }
        return view("admin.settings.sections.{$section}", $data);
    }

    private function mask(array $group, array $keys): array
    {
        $masked = [];
        foreach ($this->plain($group, $keys) as $key => $plain) {
            $masked[$key] = str_repeat('•', max(strlen($plain) - 4, 0)).substr($plain, -4);
        }

        return $masked;
    }

    private function plain(array $group, array $keys): array
    {
        $plain = [];
        foreach ($keys as $key) {
            if (! empty($group[$key])) {
                try {
                    $plain[$key] = Crypt::decryptString($group[$key]);
                } catch (\Throwable) {
                    // Fallback: value may not be encrypted yet (e.g. ERP token stored plaintext).
                    // Mirrors ErpApiAuth's own decrypt-failure fallback.
                    $plain[$key] = $group[$key];
                }
            }
        }

        return $plain;
    }
}
