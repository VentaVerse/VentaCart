<?php

namespace App\Http\Controllers\Admin\Sales;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use App\Models\Review;
use App\Models\ReviewCoupon;
use App\Models\Setting;
use App\Services\TemplateMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'pending');
        $search = $request->get('search');

        $query = Review::with('product')->whereNotNull('rating');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array($tab, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $tab);
        }

        $reviews = $query->latest()->paginate(20)->withQueryString();

        $counts = [
            'pending' => Review::whereNotNull('rating')->pending()->count(),
            'approved' => Review::whereNotNull('rating')->approved()->count(),
            'rejected' => Review::whereNotNull('rating')->rejected()->count(),
        ];

        // Load settings + order statuses for the Settings tab
        $settings = [];
        foreach (Setting::all() as $s) {
            $settings[$s->group][$s->key] = $s->value;
        }
        $orderStatuses = OrderStatus::where('store_id', (int) config('app.store_id', 1))
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $customerGroups = \App\Models\CustomerGroup::orderBy('name')->get();
        $reviewEmailTemplates = \App\Models\EmailTemplate::where('slug', 'like', 'review%')->orWhere('type', 'customer')->get();

        return view('admin.sales.reviews.index', compact('reviews', 'tab', 'search', 'counts', 'settings', 'orderStatuses', 'customerGroups', 'reviewEmailTemplates'));
    }

    public function show(Review $review)
    {
        $review->load(['product.images', 'images', 'coupon']);
        return view('admin.sales.reviews.show', compact('review'));
    }

    public function approve(Review $review)
    {
        $review->update([
            'status' => 'approved',
            'published_at' => now(),
        ]);

        // Check if coupon reward is enabled
        $couponEnabled = Setting::where('group', 'reviews')->where('key', 'coupon_enabled')->value('value');

        if ($couponEnabled && !$review->coupon) {
            $couponType = Setting::where('group', 'reviews')->where('key', 'coupon_type')->value('value') ?: 'percent';
            $couponValue = (float) (Setting::where('group', 'reviews')->where('key', 'coupon_value')->value('value') ?: 10);
            $couponExpiryDays = (int) (Setting::where('group', 'reviews')->where('key', 'coupon_expiry_days')->value('value') ?: 30);

            $couponCode = 'REV-' . strtoupper(Str::random(8));

            ReviewCoupon::create([
                'review_id' => $review->id,
                'coupon_code' => $couponCode,
                'discount_type' => $couponType,
                'discount_value' => $couponValue,
                'expires_at' => now()->addDays($couponExpiryDays),
            ]);

            $storeName = Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?: config('app.name');
            $discountLabel = $couponType === 'percent'
                ? "{$couponValue}%"
                : '₱' . number_format($couponValue, 2);

            TemplateMailService::send('review_thankyou', $review->email, [
                'customer_name' => $review->name,
                'product_name' => $review->product->name ?? 'Product',
                'coupon_code' => $couponCode,
                'discount_label' => $discountLabel,
                'expires_at' => now()->addDays($couponExpiryDays)->format('M j, Y'),
                'store_name' => $storeName,
            ]);
        }

        return back()->with('success', 'Review approved.');
    }

    public function reject(Review $review)
    {
        $review->update(['status' => 'rejected']);
        return back()->with('success', 'Review rejected.');
    }

    public function destroy(Review $review)
    {
        $review->delete();
        return back()->with('success', 'Review deleted.');
    }
}
