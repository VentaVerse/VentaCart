<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Models\Coupon;
use App\Models\CouponRestriction;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'expired') {
                $query->where('date_end', '<', now()->toDateString());
            }
        }

        $coupons = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create()
    {
        $customerGroups = CustomerGroup::orderBy('name')->get();

        return view('admin.coupons.form', [
            'coupon' => null,
            'customerGroups' => $customerGroups,
            'restrictions' => ['product' => [], 'category' => [], 'brand' => []],
        ]);
    }

    public function store(CouponRequest $request)
    {
        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['free_shipping'] = $request->boolean('free_shipping');
        $validated['discount_value'] = $validated['discount_value'] ?? 0;

        DB::transaction(function () use ($validated, $request) {
            $coupon = Coupon::create($validated);
            $this->syncRestrictions($coupon, $request);
        });

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon created successfully.');
    }

    public function edit(Coupon $coupon)
    {
        $customerGroups = CustomerGroup::orderBy('name')->get();

        $restrictions = [
            'product' => $coupon->restrictions()->where('restriction_type', 'product')->pluck('restriction_id')->toArray(),
            'category' => $coupon->restrictions()->where('restriction_type', 'category')->pluck('restriction_id')->toArray(),
            'brand' => $coupon->restrictions()->where('restriction_type', 'brand')->pluck('restriction_id')->toArray(),
        ];

        return view('admin.coupons.form', compact('coupon', 'customerGroups', 'restrictions'));
    }

    public function update(CouponRequest $request, Coupon $coupon)
    {
        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['free_shipping'] = $request->boolean('free_shipping');
        $validated['discount_value'] = $validated['discount_value'] ?? 0;

        DB::transaction(function () use ($coupon, $validated, $request) {
            $coupon->update($validated);
            $this->syncRestrictions($coupon, $request);
        });

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon deleted successfully.');
    }

    protected function syncRestrictions(Coupon $coupon, Request $request): void
    {
        $coupon->restrictions()->delete();

        $types = ['product', 'category', 'brand'];

        foreach ($types as $type) {
            $field = "restriction_{$type}s";
            $raw = $request->input($field, '');

            if (empty($raw)) {
                continue;
            }

            $ids = collect(explode(',', $raw))
                ->map(fn ($v) => trim($v))
                ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
                ->unique()
                ->values();

            foreach ($ids as $id) {
                CouponRestriction::create([
                    'coupon_id' => $coupon->id,
                    'restriction_type' => $type,
                    'restriction_id' => (int) $id,
                ]);
            }
        }
    }
}
