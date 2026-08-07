<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethodRestriction;
use App\Services\PaymentManager;
use Illuminate\Http\Request;

class PaymentRestrictionController extends Controller
{
    /**
     * The standalone restrictions index/create/edit pages were absorbed
     * into the Availability tab of the payment method detail page
     * (admin-v2 sales-channels plan, Task 5) — their views are gone, so
     * these GET actions are now pure redirects. Kept as controller methods
     * (rather than route closures) for testability and to keep them
     * consistent with the rest of this controller's actions.
     */
    public function index()
    {
        return redirect()->route('admin.payment-settings.index');
    }

    public function create()
    {
        return redirect()->route('admin.payment-settings.index');
    }

    public function store(Request $request)
    {
        $validated = $this->validateRestriction($request);
        $this->cleanArrayFields($validated);

        PaymentMethodRestriction::create($validated);

        return redirect()->route('admin.payment-settings.show', ['method' => $validated['payment_method'], 'tab' => 'availability'])
            ->with('success', 'Payment restriction created.');
    }

    public function edit(PaymentMethodRestriction $paymentRestriction)
    {
        return redirect()->route('admin.payment-settings.show', ['method' => $paymentRestriction->payment_method, 'tab' => 'availability']);
    }

    public function update(Request $request, PaymentMethodRestriction $paymentRestriction)
    {
        $validated = $this->validateRestriction($request, $paymentRestriction);
        $this->cleanArrayFields($validated);

        $paymentRestriction->update($validated);

        return redirect()->route('admin.payment-settings.show', ['method' => $paymentRestriction->payment_method, 'tab' => 'availability'])
            ->with('success', 'Payment restriction updated.');
    }

    public function destroy(PaymentMethodRestriction $paymentRestriction)
    {
        $method = $paymentRestriction->payment_method;
        $paymentRestriction->delete();

        return redirect()->route('admin.payment-settings.show', ['method' => $method, 'tab' => 'availability'])
            ->with('success', 'Payment restriction deleted.');
    }

    public function toggleActive(PaymentMethodRestriction $paymentRestriction)
    {
        $paymentRestriction->update(['is_active' => !$paymentRestriction->is_active]);

        return back()->with('success', 'Restriction ' . ($paymentRestriction->is_active ? 'enabled' : 'disabled') . '.');
    }

    private function validateRestriction(Request $request, ?PaymentMethodRestriction $existing = null): array
    {
        $rules = [
            'geo_zone_ids' => ['nullable', 'array'],
            'customer_group_ids' => ['nullable', 'array'],
            'category_ids' => ['nullable', 'array'],
            'brand_ids' => ['nullable', 'array'],
            'shipping_method_keys' => ['nullable', 'array'],
            'currency_codes' => ['nullable', 'array'],
            'min_total' => ['nullable', 'numeric', 'min:0'],
            'max_total' => ['nullable', 'numeric', 'min:0'],
            'min_weight' => ['nullable', 'numeric', 'min:0'],
            'max_weight' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'max_quantity' => ['nullable', 'integer', 'min:1'],
            'exclude_geo_zone_ids' => ['nullable', 'array'],
            'exclude_customer_group_ids' => ['nullable', 'array'],
            'exclude_category_ids' => ['nullable', 'array'],
            'exclude_brand_ids' => ['nullable', 'array'],
            'exclude_currency_codes' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];

        if (!$existing) {
            $rules['payment_method'] = ['required', 'string', 'in:' . implode(',', array_keys($this->paymentFamilies())), 'unique:payment_method_restrictions,payment_method'];
        }

        $validated = $request->validate($rules);
        $validated['is_active'] = $request->boolean('is_active');

        if (!$existing) {
            $validated['payment_method'] = $request->input('payment_method');
        }

        return $validated;
    }

    private function cleanArrayFields(array &$validated): void
    {
        $arrayFields = [
            'geo_zone_ids', 'customer_group_ids', 'category_ids', 'brand_ids',
            'shipping_method_keys', 'currency_codes',
            'exclude_geo_zone_ids', 'exclude_customer_group_ids',
            'exclude_category_ids', 'exclude_brand_ids', 'exclude_currency_codes',
        ];

        foreach ($arrayFields as $field) {
            if (empty($validated[$field])) {
                $validated[$field] = null;
            }
        }
    }

    /**
     * Payment method families available for restriction admin (base key =>
     * label). Deliberately NOT derived from PaymentManager::methods(), which
     * is enabled-state-dependent — a disabled family (or a factory with zero
     * active instances, e.g. Bank Transfer with no active accounts) returns no
     * instances there, which would make its restriction row invisible and
     * uneditable. Instead this unions:
     *  - every REGISTERED family (enabled or not), so it's always administrable;
     *  - every family already referenced by an existing restriction row, so an
     *    orphan row from a since-removed extension stays visible/editable.
     *
     * @return array<string, string>
     */
    private function paymentFamilies(): array
    {
        $keys = array_unique(array_merge(
            PaymentManager::registeredBaseKeys(),
            PaymentMethodRestriction::query()->pluck('payment_method')->all()
        ));

        $families = [];
        foreach ($keys as $baseKey) {
            $families[$baseKey] = PaymentMethodRestriction::getPaymentMethodLabel($baseKey);
        }

        return $families;
    }
}
