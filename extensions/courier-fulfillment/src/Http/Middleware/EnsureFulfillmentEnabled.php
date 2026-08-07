<?php

namespace Extensions\CourierFulfillment\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the manual Sales > Fulfillment queue (booking form, cancel, label,
 * track) behind the "Enable Fulfillment page" toggle (settings group
 * 'courier', key 'fulfillment_enabled') — the checkbox that has existed on
 * the settings page since before the admin-v2 kernel but, until now, drove
 * nothing (see CourierSettingsController's report for the full history).
 *
 * Deliberately separate from the two gates that already exist:
 *   - extension installed+enabled (Modules page / ExtensionManager) governs
 *     whether these routes are registered at ALL (Route::has(), checked by
 *     the nav registries) — disabling the extension already makes this page
 *     404 and its nav entries vanish.
 *   - CourierManager::enabled() governs whether a courier is USABLE for
 *     booking (credentials configured + its own *_enabled toggle) — the
 *     queue page itself still renders with a "no courier enabled" notice
 *     when nothing is enabled.
 * This gate is a third, narrower thing: hide the *manual* booking page for a
 * store that books exclusively through the ERP endpoint
 * (ErpFulfillmentController, unaffected by this middleware) without forcing
 * them to uninstall the extension or disable the courier the ERP still
 * needs. Off by default, matching the checkbox's own always-unchecked
 * default — an admin opts in once by enabling it on the settings page.
 *
 * Never applied to the settings routes: turning this off must never lock an
 * admin out of turning it back on.
 */
class EnsureFulfillmentEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = Setting::where('group', 'courier')
            ->where('key', 'fulfillment_enabled')
            ->value('value') === '1';

        if (!$enabled) {
            return redirect()->route('admin.fulfillment.settings')
                ->with('error', 'The Fulfillment page is turned off — enable it below to book and manage orders.');
        }

        return $next($request);
    }
}
