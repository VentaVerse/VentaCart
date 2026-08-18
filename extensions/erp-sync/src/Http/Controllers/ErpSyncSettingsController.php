<?php

namespace Extensions\ErpSync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class ErpSyncSettingsController extends Controller
{
    public function page()
    {
        $settings = [];
        foreach (Setting::where('group', 'api')->get() as $s) {
            $settings['api'][$s->key] = $s->value;
        }

        // Same masking contract as the old Settings section: decrypt when
        // possible, otherwise treat the stored value as plaintext (early
        // tokens were stored raw), and always keep the last 4 characters.
        $maskedKeys = [];
        $stored = $settings['api']['erp_token'] ?? null;
        if ($stored) {
            try {
                $plain = Crypt::decryptString($stored);
            } catch (\Throwable) {
                $plain = $stored;
            }
            $maskedKeys['erp_token'] = str_repeat('•', max(strlen($plain) - 4, 0)) . substr($plain, -4);
        }

        return view('erp-sync::settings', compact('settings', 'maskedKeys'));
    }
}
