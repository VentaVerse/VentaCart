<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'hitpay_sandbox_api_key',
        'hitpay_sandbox_salt',
        'hitpay_production_api_key',
        'hitpay_production_salt',
        'dph_email',
        'dph_password',
        'dhl_site_id',
        'dhl_password',
        'claude_api_key',
    ];

    public function index()
    {
        $settings = [];
        foreach (Setting::all() as $s) {
            $settings[$s->group][$s->key] = $s->value;
        }

        $currencies = Currency::orderBy('is_default', 'desc')->orderBy('code')->get();

        // Build masked previews for encrypted fields
        $maskedKeys = [];
        foreach (self::ENCRYPTED_KEYS as $key) {
            // Check payment, shipping, and seo groups for encrypted keys
            $encrypted = $settings['payment'][$key] ?? $settings['shipping'][$key] ?? $settings['seo'][$key] ?? null;
            if ($encrypted) {
                try {
                    $plain = Crypt::decryptString($encrypted);
                    $maskedKeys[$key] = str_repeat('*', max(0, strlen($plain) - 4)) . substr($plain, -4);
                } catch (\Throwable) {
                    $maskedKeys[$key] = '********';
                }
            }
        }

        return view('admin.settings.index', compact('settings', 'currencies', 'maskedKeys'));
    }

    public function update(Request $request)
    {
        $group = $request->input('group');
        $data = $request->input('settings', []);

        foreach ($data as $key => $value) {
            // Skip empty encrypted fields (don't overwrite existing)
            if (in_array($key, self::ENCRYPTED_KEYS)) {
                if (empty($value)) {
                    continue;
                }
                $value = Crypt::encryptString($value);
            }

            if (is_array($value)) {
                $value = json_encode($value);
            }
            Setting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value]
            );
        }

        app(ThemeService::class)->clearCache();

        // Custom redirect (e.g., DPH page has its own settings forms)
        if ($redirect = $request->input('_redirect')) {
            $parsed = parse_url($redirect);
            if (!empty($parsed['host']) || !str_starts_with($parsed['path'] ?? '', '/')) {
                $redirect = null;
            }
            if ($redirect) {
                return redirect($redirect)->with('success', ucfirst($group) . ' settings updated.');
            }
        }

        // Determine which tab to return to
        $requestTab = $request->input('_tab');
        $tab = match (true) {
            $requestTab !== null => $requestTab,
            in_array($group, ['identity', 'general', 'stock']) => 'general',
            default => $group,
        };

        return redirect()->route('admin.settings.index', ['tab' => $tab])->with('success', ucfirst($group) . ' settings updated.');
    }

    public function testMail(Request $request)
    {
        $request->validate(['to' => 'required|email']);

        try {
            Mail::raw('This is a test email from ' . config('app.name') . '. If you received this, your mail configuration is working correctly.', function ($message) use ($request) {
                $message->to($request->input('to'))
                    ->subject('Test Email - ' . config('app.name'));
            });

            return back()->with('success', 'Test email sent to ' . $request->input('to') . '.')->with('tab', 'mail');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send: ' . $e->getMessage())->with('tab', 'mail');
        }
    }

    public function testDphConnection()
    {
        try {
            // Force a fresh login (clear any cached token so we actually test credentials)
            \Illuminate\Support\Facades\Cache::forget('dph_auth');

            $dph = app(\App\Services\DeliveryPhService::class);
            $dph->login();

            return back()->with('success', 'DPH connection successful! Credentials are valid.')->with('tab', 'dph');
        } catch (\Throwable $e) {
            return back()->with('error', 'DPH connection failed: ' . $e->getMessage())->with('tab', 'dph');
        }
    }

    public function generateErpToken()
    {
        $token = bin2hex(random_bytes(32));

        Setting::updateOrCreate(
            ['group' => 'api', 'key' => 'erp_token'],
            ['value' => $token]
        );

        return back()->with('success', 'ERP API token generated.')->with('tab', 'api')->with('new_erp_token', $token);
    }

    public function uploadGscCredentials(Request $request)
    {
        $request->validate(['gsc_credentials' => 'required|file|mimes:json|max:50']);
        $request->file('gsc_credentials')->storeAs('', 'gsc-credentials.json');

        return back()->with('success', 'Google Search Console credentials uploaded.')->with('tab', 'seo');
    }

    public function testDhl()
    {
        $service = new \App\Services\DhlExpressService();

        if (!$service->isConfigured()) {
            return back()->with('error', 'DHL credentials not configured. Save them first.')->with('tab', 'dhl');
        }

        try {
            $quotes = $service->getRateQuote(
                ['country_code' => 'PH', 'city' => 'Manila', 'postal_code' => '1800'],
                ['country_code' => 'SG', 'city' => 'Singapore', 'postal_code' => '048624'],
                [['weight' => 0.5, 'length' => 10, 'width' => 10, 'height' => 10]]
            );

            if (empty($quotes)) {
                return back()->with('error', 'DHL returned no services. Check your account status.')->with('tab', 'dhl');
            }

            $summary = collect($quotes)->map(function ($q) {
                return $q['name'] . ': ' . ($q['cost'] > 0 ? $q['currency'] . ' ' . $q['cost'] : 'No rate');
            })->join(', ');

            return back()->with('success', 'DHL connection successful. Services: ' . $summary)->with('tab', 'dhl');
        } catch (\Throwable $e) {
            return back()->with('error', 'DHL test failed: ' . $e->getMessage())->with('tab', 'dhl');
        }
    }

    public function generateDphWebhookSecret()
    {
        $secret = bin2hex(random_bytes(32));

        \App\Models\Setting::updateOrCreate(
            ['group' => 'shipping', 'key' => 'dph_webhook_secret'],
            ['value' => $secret]
        );

        return back()->with('success', 'Webhook secret generated.')->with('tab', 'dph');
    }
}
