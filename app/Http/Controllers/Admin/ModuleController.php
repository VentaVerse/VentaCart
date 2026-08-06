<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Cart;
use App\Models\SeoSuggestion;
use App\Models\Setting;
use App\Models\StockNotification;
use Illuminate\Support\Facades\Route;

class ModuleController extends Controller
{
    public function index()
    {
        $automations = Automation::whereIn('key', ['seo_generate', 'stock_notify', 'stock_check_low'])->get()->keyBy('key');
        $flags = Setting::whereIn('group', ['stock', 'abandoned_cart'])->get()
            ->groupBy('group')->map(fn ($rows) => $rows->pluck('value', 'key'));

        $backInStock = (bool) ($automations['stock_notify']->enabled ?? false);
        $lowStock = ($flags['stock']['low_stock_alert_enabled'] ?? '0') === '1'
            && (bool) ($automations['stock_check_low']->enabled ?? false);
        $stockAlertEnabled = $backInStock || $lowStock;
        $seoAutoEnabled = (bool) ($automations['seo_generate']->enabled ?? false);
        $abandonedCartEnabled = ($flags['abandoned_cart']['enabled'] ?? '0') === '1';
        $bannersInstalled = class_exists(\Extensions\Banners\BannerServiceProvider::class);

        $modules = [
            [
                'key' => 'banners',
                'name' => 'Banners',
                'description' => 'Homepage and promotional banner management.',
                'icon' => 'media',
                'enabled' => $bannersInstalled,
                'status_label' => $bannersInstalled ? 'Installed' : 'Not installed',
                'stats' => null,
                'configure' => $bannersInstalled && Route::has('admin.banners.index') ? route('admin.banners.index') : null,
            ],
            [
                'key' => 'stock-alerts',
                'name' => 'Stock Alerts',
                'description' => 'Back-in-stock customer emails and low-stock admin warnings.',
                'icon' => 'warning',
                'enabled' => $stockAlertEnabled,
                'status_label' => 'Back-in-stock ' . ($backInStock ? 'on' : 'off') . ' · Low-stock ' . ($lowStock ? 'on' : 'off'),
                'tone' => ($backInStock && $lowStock) ? 'success' : (($backInStock || $lowStock) ? 'warning' : 'neutral'),
                'stats' => StockNotification::where('status', 'waiting')->count() . ' waiting',
                'configure' => route('admin.stock-notifications.index'),
            ],
            [
                'key' => 'seo',
                'name' => 'SEO',
                'description' => 'AI meta suggestions, redirects, and Search Console.',
                'icon' => 'seo',
                'enabled' => $seoAutoEnabled,
                'status_label' => $seoAutoEnabled ? 'Auto-generating' : 'Manual only',
                'stats' => SeoSuggestion::where('status', 'pending')->count() . ' pending suggestions',
                'configure' => route('admin.extensions.seo.settings'),
            ],
            [
                'key' => 'abandoned-carts',
                'name' => 'Abandoned Carts',
                'description' => 'Cart recovery email rules and tracking.',
                'icon' => 'cart',
                'enabled' => $abandonedCartEnabled,
                'status_label' => $abandonedCartEnabled ? 'Active' : 'Disabled',
                'stats' => Cart::whereHas('items')->where('updated_at', '<', now()->subHour())->count() . ' tracked carts',
                'configure' => route('admin.abandoned-carts.settings'),
            ],
        ];

        return view('admin.modules.index', ['modules' => $modules]);
    }
}
