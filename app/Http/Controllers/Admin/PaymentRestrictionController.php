<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\GeoZone;
use App\Models\PaymentMethodRestriction;
use App\Services\PaymentManager;
use Illuminate\Http\Request;

class PaymentRestrictionController extends Controller
{
    public function index()
    {
        $families = $this->paymentFamilies();

        $restrictions = PaymentMethodRestriction::whereIn('payment_method', array_keys($families))
            ->get()
            ->keyBy('payment_method');

        // Build a restriction (or empty placeholder) for each payment method family
        $methods = collect($families)->map(function ($label, $key) use ($restrictions) {
            return [
                'key' => $key,
                'label' => $label,
                'restriction' => $restrictions->get($key),
            ];
        })->values();

        return view('admin.payment-restrictions.index', [
            'methods' => $methods,
            ...$this->getFormData(),
        ]);
    }

    public function create()
    {
        $existing = PaymentMethodRestriction::pluck('payment_method')->all();
        $availableMethods = array_diff(array_keys($this->paymentFamilies()), $existing);

        if (empty($availableMethods)) {
            return redirect()->route('admin.payment-restrictions.index')
                ->with('info', 'All payment methods already have restrictions configured.');
        }

        return view('admin.payment-restrictions.form', [
            'restriction' => null,
            'availableMethods' => $availableMethods,
            ...$this->getFormData(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRestriction($request);
        $this->cleanArrayFields($validated);

        PaymentMethodRestriction::create($validated);

        return redirect()->route('admin.payment-restrictions.index', ['tab' => $validated['payment_method']])
            ->with('success', 'Payment restriction created.');
    }

    public function edit(PaymentMethodRestriction $paymentRestriction)
    {
        return view('admin.payment-restrictions.form', [
            'restriction' => $paymentRestriction,
            'availableMethods' => [],
            ...$this->getFormData(),
        ]);
    }

    public function update(Request $request, PaymentMethodRestriction $paymentRestriction)
    {
        $validated = $this->validateRestriction($request, $paymentRestriction);
        $this->cleanArrayFields($validated);

        $paymentRestriction->update($validated);

        return redirect()->route('admin.payment-restrictions.index', ['tab' => $paymentRestriction->payment_method])
            ->with('success', 'Payment restriction updated.');
    }

    public function destroy(PaymentMethodRestriction $paymentRestriction)
    {
        $method = $paymentRestriction->payment_method;
        $paymentRestriction->delete();

        return redirect()->route('admin.payment-restrictions.index', ['tab' => $method])
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

    private function getFormData(): array
    {
        return [
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::whereNull('parent_id')->with('children')->orderBy('name')->get(),
            'customerGroups' => CustomerGroup::orderBy('name')->get(),
            'geoZones' => GeoZone::where('is_active', true)->orderBy('sort_order')->get(),
            'shippingMethods' => [
                ['key' => 'free_shipping', 'name' => 'Free Shipping'],
                ['key' => 'weight_based', 'name' => 'Weight-Based'],
                ['key' => 'flat_rate', 'name' => 'Flat Rate'],
                ['key' => 'dhl', 'name' => 'DHL Express'],
            ],
            'currencies' => \App\Models\Currency::orderByDesc('is_default')->orderBy('code')->get(),
        ];
    }
}
