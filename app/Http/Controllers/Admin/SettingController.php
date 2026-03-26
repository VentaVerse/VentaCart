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
        'turnstile_secret_key',
        'erp_token',
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
            $encrypted = $settings['payment'][$key] ?? $settings['shipping'][$key] ?? $settings['seo'][$key] ?? $settings['security'][$key] ?? null;
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

    private const RESERVED_PREFIXES = [
        'login', 'register', 'logout', 'forgot-password', 'reset-password',
        'confirm-password', 'verify-email', 'account', 'api', 'storage',
        'sitemap', 'checkout', 'contact', 'brand', 'brands', 'category',
        'categories', 'product', 'products', 'blog', 'cart', 'search',
        'wishlist', 'compare', 'page', 'pages',
    ];

    public function update(Request $request)
    {
        $group = $request->input('group');
        $data = $request->input('settings', []);

        // Validate admin prefix if being updated
        if ($group === 'security' && isset($data['admin_prefix'])) {
            $prefix = $data['admin_prefix'];
            if (!preg_match('/^[a-z][a-z0-9-]*$/', $prefix) || strlen($prefix) < 2 || strlen($prefix) > 50) {
                return back()->with('error', 'Admin path must be 2-50 characters, lowercase letters, numbers and hyphens only, starting with a letter.')->with('tab', 'security');
            }
            if (in_array($prefix, self::RESERVED_PREFIXES)) {
                return back()->with('error', 'That path is reserved and cannot be used as the admin URL.')->with('tab', 'security');
            }
        }

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

        // Also save per-theme copy when saving colors or fonts
        if (in_array($group, ['colors', 'fonts'])) {
            $activeTheme = app(ThemeService::class)->getActiveTheme();
            if ($activeTheme) {
                foreach ($data as $key => $value) {
                    if (in_array($key, self::ENCRYPTED_KEYS) && empty($value)) continue;
                    $val = is_array($value) ? json_encode($value) : $value;
                    Setting::updateOrCreate(
                        ['group' => "{$group}__{$activeTheme}", 'key' => $key],
                        ['value' => $val]
                    );
                }
            }
        }

        app(ThemeService::class)->clearCache();

        // Redirect to new admin URL after prefix change
        if ($group === 'security' && isset($data['admin_prefix'])) {
            $newPrefix = $data['admin_prefix'];
            return redirect('/' . $newPrefix . '/settings?tab=security')->with('success', 'Admin URL changed to /' . $newPrefix);
        }

        // Custom redirect (e.g., shipping settings forms redirect back to their own page)
        if ($redirect = $request->input('_redirect')) {
            $parsed = parse_url($redirect);
            $path = $parsed['path'] ?? '/';
            if (str_starts_with($path, '/admin/')) {
                $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
                return redirect($path . $query)->with('success', ucfirst($group) . ' settings updated.');
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
        $request->file('gsc_credentials')->storeAs('credentials', 'gsc-credentials.json', 'local');

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
