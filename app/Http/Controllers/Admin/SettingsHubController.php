<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SettingsNav;
use Illuminate\Http\Request;

class SettingsHubController extends Controller
{
    /** Legacy ?tab= values from the old tabbed settings page → section keys. */
    private const TAB_MAP = [
        'general' => 'general',
        'currency' => 'currencies',
        'checkout' => 'checkout',
        'mail' => 'mail',
        'api' => 'api',
        'security' => 'security',
        'automations' => 'automations',
    ];

    public function index(Request $request)
    {
        $tab = $request->query('tab');
        if ($tab !== null && isset(self::TAB_MAP[$tab])) {
            return redirect()->route('admin.settings.section', self::TAB_MAP[$tab]);
        }

        return view('admin.settings.hub', [
            'groups' => SettingsNav::groups($request->user()),
        ]);
    }
}
