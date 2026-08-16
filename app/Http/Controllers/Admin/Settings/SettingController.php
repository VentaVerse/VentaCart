<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
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
        'dhl_site_id',
        'dhl_password',
        'claude_api_key',
        'turnstile_secret_key',
        'erp_token',
    ];

    private const RESERVED_PREFIXES = [
        'login', 'register', 'logout', 'forgot-password', 'reset-password',
        'confirm-password', 'verify-email', 'account', 'api', 'storage',
        'sitemap', 'checkout', 'contact', 'brand', 'brands', 'category',
        'categories', 'product', 'products', 'blog', 'cart', 'search',
        'wishlist', 'compare', 'page', 'pages',
    ];

    public function update(Request $request)
    {
        // v3 doctrine: one save may carry several setting groups at once
        // (groups[checkout][...], groups[stock][...]) because one page is
        // one save scope. Each group runs the same per-key pipeline.
        if (is_array($request->input('groups'))) {
            return $this->updateGroups($request);
        }

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
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            return redirect('/' . $newPrefix . '/settings?tab=security')->with('success', 'Admin URL changed to /' . $newPrefix);
        }

        // Custom redirect (e.g., shipping settings forms redirect back to their own page)
        if ($redirect = $request->input('_redirect')) {
            $parsed = parse_url($redirect);
            $path = $parsed['path'] ?? '/';
            $adminPrefix = '/' . $request->segment(1) . '/';
            if (str_starts_with($path, $adminPrefix)) {
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

    private function updateGroups(Request $request)
    {
        $groups = $request->input('groups', []);
        $newPrefix = null;

        // Validate everything BEFORE the first write: a rejected save
        // must leave every group untouched, whatever order they arrived in.
        $prefix = $groups['security']['admin_prefix'] ?? null;
        if ($prefix !== null) {
            if (!preg_match('/^[a-z][a-z0-9-]*$/', $prefix) || strlen($prefix) < 2 || strlen($prefix) > 50) {
                return back()->with('error', 'Admin path must be 2-50 characters, lowercase letters, numbers and hyphens only, starting with a letter.');
            }
            if (in_array($prefix, self::RESERVED_PREFIXES)) {
                return back()->with('error', 'That path is reserved and cannot be used as the admin URL.');
            }
            if ($prefix !== ($request->segment(1) ?? '')) {
                $newPrefix = $prefix;
            }
        }

        foreach ($groups as $group => $data) {
            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $key => $value) {
                if (in_array($key, self::ENCRYPTED_KEYS)) {
                    if (empty($value)) {
                        continue;
                    }
                    $value = Crypt::encryptString($value);
                }
                // A secret field submitted blank means "keep what is
                // stored", never "clear it" (the x-admin3.secret contract).
                if ($value === null && str_contains($key, 'password')) {
                    continue;
                }
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                Setting::updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => $value]
                );
            }
        }

        app(ThemeService::class)->clearCache();

        if ($newPrefix !== null) {
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            return redirect('/' . $newPrefix . '/settings/security')
                ->with('success', 'Settings saved. The admin now lives at /' . $newPrefix . '.');
        }

        if ($redirect = $request->input('_redirect')) {
            $parsed = parse_url($redirect);
            $path = $parsed['path'] ?? '/';
            $adminPrefix = '/' . $request->segment(1) . '/';
            if (str_starts_with($path, $adminPrefix)) {
                $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
                return redirect($path . $query)->with('success', 'Settings saved.');
            }
        }

        return redirect()->route('admin.settings.index')->with('success', 'Settings saved.');
    }

    public function testMail(Request $request)
    {
        $request->validate(['to' => 'required|email']);

        // v3: a test button tests the values currently typed, never the
        // stale saved ones. Overrides apply to this request's mailer only;
        // nothing is persisted. A blank password falls back to the stored
        // one so testing does not require retyping a saved secret.
        $override = $request->input('override', []);
        if (is_array($override) && $override !== []) {
            if (($override['driver'] ?? 'smtp') === 'smtp') {
                if (empty($override['host'])) {
                    return $this->testMailResult($request, false, 'SMTP host is required before a test can run.');
                }

                // The stored password rides ONLY when the typed transport is
                // the saved one. A blank password with a changed host, port,
                // or username would hand the stored credential to whatever
                // server the host field names, which is an exfiltration
                // primitive, not a convenience.
                $stored = Setting::where('group', 'mail')
                    ->whereIn('key', ['host', 'port', 'username', 'password'])
                    ->pluck('value', 'key');
                $sameTransport = ($override['host'] ?? '') === ($stored['host'] ?? '')
                    && (string) ($override['port'] ?? '') === (string) ($stored['port'] ?? '')
                    && ($override['username'] ?? '') === ($stored['username'] ?? '');
                $storedPassword = $sameTransport ? ($stored['password'] ?? null) : null;

                if (($override['password'] ?? '') === '' && !$sameTransport && !empty($stored['password'])) {
                    return $this->testMailResult($request, false, 'The server details changed. Type the password to test the new transport.');
                }

                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $override['host'],
                    'mail.mailers.smtp.port' => (int) ($override['port'] ?? 587),
                    'mail.mailers.smtp.username' => $override['username'] ?? null,
                    'mail.mailers.smtp.password' => ($override['password'] ?? '') !== '' ? $override['password'] : $storedPassword,
                    'mail.mailers.smtp.encryption' => ($override['encryption'] ?? 'tls') ?: null,
                    // A test must answer fast; a dead host at the transport's
                    // default timeout leaves the button spinning for a minute.
                    'mail.mailers.smtp.timeout' => 10,
                ]);
            } else {
                config(['mail.default' => 'sendmail']);
            }
            if (!empty($override['from_address'])) {
                config([
                    'mail.from.address' => $override['from_address'],
                    'mail.from.name' => $override['from_name'] ?? config('mail.from.name'),
                ]);
            }
        }

        try {
            Mail::raw('This is a test email from ' . config('app.name') . '. If you received this, your mail configuration is working correctly.', function ($message) use ($request) {
                $message->to($request->input('to'))
                    ->subject('Test Email - ' . config('app.name'));
            });

            return $this->testMailResult($request, true, 'Test email sent to ' . $request->input('to') . '.');
        } catch (\Throwable $e) {
            return $this->testMailResult($request, false, 'Failed to send: ' . $e->getMessage());
        }
    }

    private function testMailResult(Request $request, bool $ok, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message]);
        }

        return back()->with($ok ? 'success' : 'error', $message)->with('tab', 'mail');
    }

    /**
     * Reveal a stored secret on demand, so plaintext never rides in a
     * rendered page. Hard allowlist: only keys a settings screen offers a
     * View button for. Everything else 404s.
     */
    private const REVEALABLE = [
        'security' => ['turnstile_secret_key'],
    ];

    public function revealSecret(Request $request)
    {
        $group = (string) $request->input('group');
        $key = (string) $request->input('key');
        abort_unless(in_array($key, self::REVEALABLE[$group] ?? [], true), 404);

        $value = Setting::where('group', $group)->where('key', $key)->value('value');
        if ($value !== null && in_array($key, self::ENCRYPTED_KEYS)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Throwable) {
                return response()->json(['ok' => false, 'message' => 'The stored key could not be decrypted.']);
            }
        }

        if ($value === null || $value === '') {
            return response()->json(['ok' => false, 'message' => 'No key is stored.']);
        }

        return response()->json(['ok' => true, 'value' => $value]);
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

}
