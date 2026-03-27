<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCartNotification;
use App\Models\AbandonedCartRule;
use App\Models\Cart;
use App\Models\CustomerGroup;
use App\Models\EmailTemplate;
use App\Models\OrderProduct;
use App\Models\Setting;
use App\Services\TemplateMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AbandonedCartController extends Controller
{
    private const ABANDONED_CART_PLACEHOLDERS = ['customer_name', 'cart_items', 'cart_total', 'cart_url', 'store_name'];

    private const TEST_DATA = [
        'customer_name' => 'John Doe',
        'cart_items' => '<p style="margin: 4px 0; font-size: 14px; color: #e8e8e8;">1x Boss DS-1 Distortion Pedal — 2,499.00</p><p style="margin: 4px 0; font-size: 14px; color: #e8e8e8;">1x Mogami Gold Cable 10ft — 2,100.00</p>',
        'cart_total' => '4,599.00',
        'cart_url' => '#',
        'store_name' => 'NewGearDay',
    ];
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

    public function massDelete(Request $request)
    {
        $type = $request->input('type');

        $query = Cart::whereHas('items')
            ->where('updated_at', '<', now()->subHour());

        if ($type === 'guest') {
            $query->whereNull('customer_id');
        }

        $count = 0;
        $query->each(function ($cart) use (&$count) {
            $cart->items()->delete();
            $cart->delete();
            $count++;
        });

        $label = $type === 'guest' ? 'guest ' : '';

        return redirect()->route('admin.abandoned-carts.index')
            ->with('success', "{$count} {$label}abandoned cart(s) deleted.");
    }

    // --- Templates ---

    public function templates()
    {
        $templates = EmailTemplate::where('slug', 'like', 'abandoned_cart_%')
            ->orderBy('name')
            ->get();

        return view('admin.abandoned-carts.templates', compact('templates'));
    }

    public function createTemplate()
    {
        $placeholders = self::ABANDONED_CART_PLACEHOLDERS;

        return view('admin.abandoned-carts.template-form', [
            'emailTemplate' => null,
            'placeholders' => $placeholders,
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|regex:/^abandoned_cart_/|unique:email_templates,slug',
            'subject' => 'required|string|max:500',
            'body' => 'required|string',
        ]);

        $validated['store_id'] = 1;
        $validated['type'] = 'customer';
        $validated['is_active'] = true;

        EmailTemplate::create($validated);

        return redirect()->route('admin.abandoned-carts.templates')
            ->with('success', 'Template created.');
    }

    public function editTemplate(EmailTemplate $emailTemplate)
    {
        $placeholders = self::ABANDONED_CART_PLACEHOLDERS;

        return view('admin.abandoned-carts.template-form', compact('emailTemplate', 'placeholders'));
    }

    public function updateTemplate(Request $request, EmailTemplate $emailTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:500',
            'body' => 'required|string',
        ]);

        $emailTemplate->update($validated);

        return redirect()->route('admin.abandoned-carts.templates')
            ->with('success', 'Template updated.');
    }

    public function toggleTemplate(EmailTemplate $emailTemplate)
    {
        $emailTemplate->update(['is_active' => !$emailTemplate->is_active]);

        return back()->with('success', 'Template ' . ($emailTemplate->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function testTemplate(EmailTemplate $emailTemplate)
    {
        $adminEmail = Auth::user()->email;
        $success = TemplateMailService::send($emailTemplate->slug, $adminEmail, self::TEST_DATA);

        return back()->with(
            $success ? 'success' : 'error',
            $success ? "Test email sent to {$adminEmail}." : 'Failed to send test email.'
        );
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
        $templates = EmailTemplate::where('slug', 'like', 'abandoned_cart_%')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
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
        $templates = EmailTemplate::where('slug', 'like', 'abandoned_cart_%')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
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

        $templates = EmailTemplate::where('slug', 'like', 'abandoned_cart_%')
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

    // --- Settings ---

    public function settings()
    {
        $enabled = Setting::where('group', 'abandoned_cart')
            ->where('key', 'enabled')
            ->value('value') === '1';

        return view('admin.abandoned-carts.settings', compact('enabled'));
    }

    public function updateSettings(Request $request)
    {
        Setting::updateOrCreate(
            ['group' => 'abandoned_cart', 'key' => 'enabled'],
            ['value' => $request->boolean('enabled') ? '1' : '0']
        );

        return redirect()->route('admin.abandoned-carts.settings')
            ->with('success', 'Settings updated.');
    }
}
