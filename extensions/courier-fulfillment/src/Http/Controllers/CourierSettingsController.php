<?php

namespace Extensions\CourierFulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Courier settings — self-contained (own encryption, own save). Stores under
 * settings group 'courier'. Credentials are encrypted at rest and masked in
 * the UI. The webhook URL is derived from THIS install's APP_URL so each
 * deployed store shows its own copy-able URL.
 */
class CourierSettingsController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'quadx_staging_api_key',
        'quadx_staging_secret_key',
        'quadx_production_api_key',
        'quadx_production_secret_key',
    ];

    /**
     * The ONLY keys writable via this form. Anything else in the request is
     * ignored — prevents arbitrary writes into the 'courier' settings group
     * (e.g. overwriting the server-generated quadx_webhook_token, which is
     * deliberately absent here).
     */
    private const ALLOWED_KEYS = [
        'quadx_enabled',
        'quadx_mode',
        'quadx_default_service',
        'quadx_default_parcel',
        'quadx_staging_api_key',
        'quadx_staging_secret_key',
        'quadx_production_api_key',
        'quadx_production_secret_key',
    ];

    public function index()
    {
        $settings = Setting::where('group', 'courier')->pluck('value', 'key')->toArray();

        // Ensure a stable webhook token exists for this store.
        if (empty($settings['quadx_webhook_token'])) {
            $token = Str::random(40);
            Setting::updateOrCreate(
                ['group' => 'courier', 'key' => 'quadx_webhook_token'],
                ['value' => $token]
            );
            $settings['quadx_webhook_token'] = $token;
        }

        $masked = [];
        foreach (self::ENCRYPTED_KEYS as $k) {
            if (!empty($settings[$k])) {
                try {
                    $plain = Crypt::decryptString($settings[$k]);
                    $masked[$k] = str_repeat('*', max(0, strlen($plain) - 4)) . substr($plain, -4);
                } catch (\Throwable) {
                    $masked[$k] = '********';
                }
            }
        }

        // This store's own webhook URL — copy and send to QuadX.
        $webhookUrl = rtrim((string) config('app.url'), '/')
            . '/webhooks/courier/quadx?token=' . $settings['quadx_webhook_token'];

        return view('courier-fulfillment::settings', compact('settings', 'masked', 'webhookUrl'));
    }

    public function update(Request $request)
    {
        $data = $request->input('settings', []);

        // Restrict to an explicit allowlist — never persist arbitrary request keys.
        $data = array_intersect_key($data, array_flip(self::ALLOWED_KEYS));

        foreach ($data as $key => $value) {
            if (in_array($key, self::ENCRYPTED_KEYS, true)) {
                if (empty($value)) {
                    continue; // don't overwrite stored secret with blank
                }
                $value = Crypt::encryptString($value);
            }
            if (is_array($value)) {
                $value = json_encode($value);
            }
            Setting::updateOrCreate(
                ['group' => 'courier', 'key' => $key],
                ['value' => $value]
            );
        }

        return back()->with('success', 'Courier settings saved.');
    }
}
