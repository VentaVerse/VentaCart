<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCartNotification;
use App\Models\AbandonedCartRule;
use App\Models\Cart;
use App\Models\CustomerGroup;
use App\Models\EmailTemplate;
use App\Models\OrderProduct;
use Illuminate\Http\Request;

class AbandonedCartController extends Controller
{
    public function index(Request $request)
    {
        $query = Cart::with(['customer', 'items.product'])
            ->whereHas('items')
            ->where('updated_at', '<', now()->subHour());

        if ($search = $request->input('search')) {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->input('type') === 'guest') {
            $query->whereNull('customer_id');
        } elseif ($request->input('type') === 'registered') {
            $query->whereNotNull('customer_id');
        }

        $carts = $query->orderByDesc('updated_at')->paginate(25)->withQueryString();

        $totalAbandoned = Cart::whereHas('items')->where('updated_at', '<', now()->subHour())->count();
        $totalValue = Cart::whereHas('items')
            ->where('updated_at', '<', now()->subHour())
            ->with('items')
            ->get()
            ->sum(fn ($cart) => $cart->total);

        return view('admin.abandoned-carts.index', compact('carts', 'totalAbandoned', 'totalValue'));
    }

    public function show(Cart $cart)
    {
        $cart->load(['customer', 'items.product']);

        return view('admin.abandoned-carts.show', compact('cart'));
    }

    public function destroy(Cart $cart)
    {
        $cart->items()->delete();
        $cart->delete();

        return redirect()->route('admin.abandoned-carts.index')->with('success', 'Abandoned cart deleted.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back()->with('error', 'No carts selected.');
        }

        Cart::whereIn('id', $ids)->each(function ($cart) {
            $cart->items()->delete();
            $cart->delete();
        });

        return back()->with('success', count($ids) . ' abandoned cart(s) deleted.');
    }

    // --- Rules ---

    public function rules()
    {
        $rules = AbandonedCartRule::with(['emailTemplate', 'customerGroup'])
            ->orderByDesc('priority')
            ->get();

        return view('admin.abandoned-carts.rules', compact('rules'));
    }

    public function createRule()
    {
        $templates = EmailTemplate::where('is_active', true)->orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('name')->get();

        return view('admin.abandoned-carts.rule-form', [
            'rule' => null,
            'templates' => $templates,
            'customerGroups' => $customerGroups,
        ]);
    }

    public function storeRule(Request $request)
    {
        $validated = $request->validate([
            'email_template_id' => 'required|exists:email_templates,id',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'max_sends' => 'required|integer|min:1|max:100',
            'min_cart_age_hours' => 'required|integer|min:1|max:8760',
            'priority' => 'required|integer|min:0|max:999',
            'is_active' => 'boolean',
        ]);

        $validated['store_id'] = 1;
        $validated['is_active'] = $request->boolean('is_active');

        AbandonedCartRule::create($validated);

        return redirect()->route('admin.abandoned-carts.rules')->with('success', 'Rule created.');
    }

    public function editRule(AbandonedCartRule $rule)
    {
        $templates = EmailTemplate::where('is_active', true)->orderBy('name')->get();
        $customerGroups = CustomerGroup::orderBy('name')->get();

        return view('admin.abandoned-carts.rule-form', compact('rule', 'templates', 'customerGroups'));
    }

    public function updateRule(Request $request, AbandonedCartRule $rule)
    {
        $validated = $request->validate([
            'email_template_id' => 'required|exists:email_templates,id',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
            'max_sends' => 'required|integer|min:1|max:100',
            'min_cart_age_hours' => 'required|integer|min:1|max:8760',
            'priority' => 'required|integer|min:0|max:999',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $rule->update($validated);

        return redirect()->route('admin.abandoned-carts.rules')->with('success', 'Rule updated.');
    }

    public function destroyRule(AbandonedCartRule $rule)
    {
        $rule->delete();

        return redirect()->route('admin.abandoned-carts.rules')->with('success', 'Rule deleted.');
    }

    // --- Notification Log ---

    public function notifications(Request $request)
    {
        $query = AbandonedCartNotification::with(['customer', 'cart.items', 'rule', 'emailTemplate'])
            ->orderByDesc('last_sent_at');

        if ($templateId = $request->input('template')) {
            $query->where('email_template_id', $templateId);
        }

        $notifications = $query->paginate(25)->withQueryString();

        $templates = EmailTemplate::whereIn('slug', ['abandoned_cart_reminder', 'abandoned_cart_final'])
            ->orderBy('name')
            ->get();

        return view('admin.abandoned-carts.notifications', compact('notifications', 'templates'));
    }

    // --- Recovered Carts ---

    public function recovered()
    {
        $notifications = AbandonedCartNotification::where('send_count', '>', 0)
            ->with(['customer', 'cart.items'])
            ->get();

        $recoveredCarts = [];

        foreach ($notifications as $notification) {
            $cart = $notification->cart;
            $customer = $notification->customer;

            if (!$cart || !$customer) {
                continue;
            }

            $cartProductIds = $cart->items->pluck('product_id')->unique()->toArray();

            if (empty($cartProductIds)) {
                continue;
            }

            $matchingOrderProduct = OrderProduct::whereHas('order', function ($q) use ($customer, $notification) {
                $q->where('customer_id', $customer->id)
                  ->where('created_at', '>', $notification->last_sent_at);
            })->whereIn('product_id', $cartProductIds)->with('order')->first();

            if ($matchingOrderProduct) {
                $recoveredCarts[] = [
                    'customer' => $customer,
                    'cart_items' => $cart->items->pluck('name')->implode(', '),
                    'send_count' => $notification->send_count,
                    'last_sent_at' => $notification->last_sent_at,
                    'order' => $matchingOrderProduct->order,
                    'recovery_time' => $notification->last_sent_at->diffForHumans($matchingOrderProduct->order->created_at, true),
                ];
            }
        }

        return view('admin.abandoned-carts.recovered', compact('recoveredCarts'));
    }
}
