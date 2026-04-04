<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\TemplateMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailTemplateController extends Controller
{
    private const PLACEHOLDER_MAP = [
        'order_confirmation' => ['customer_name', 'order_id', 'subtotal', 'shipping_method', 'shipping_cost', 'order_total', 'order_items', 'shipping_address', 'payment_method', 'comment', 'store_name'],
        'order_status_update' => ['customer_name', 'order_id', 'old_status', 'new_status', 'comment', 'store_name'],
        'shipping_notification' => ['customer_name', 'order_id', 'tracking_number', 'carrier_name', 'store_name'],
        'welcome' => ['customer_name', 'store_name', 'store_url'],
        'password_reset' => ['customer_name', 'reset_url', 'store_name'],
        'review_request' => ['customer_name', 'product_name', 'product_image', 'review_url', 'store_name'],
        'new_order_admin' => ['order_id', 'customer_name', 'customer_email', 'subtotal', 'shipping_method', 'shipping_cost', 'order_total', 'order_items', 'shipping_address', 'payment_method', 'comment'],
        'low_stock_admin' => ['product_name', 'product_sku', 'current_qty', 'store_name'],
    ];

    private static function testData(): array
    {
        return [
            'customer_name' => 'John Doe',
            'order_id' => '1001',
            'order_total' => '4,599.00',
            'order_items' => '<p style="margin: 4px 0; font-size: 14px; color: #e8e8e8;">1x Boss DS-1 Distortion Pedal — 2,499.00</p><p style="margin: 4px 0; font-size: 14px; color: #e8e8e8;">1x Mogami Gold Cable 10ft — 2,100.00</p>',
            'subtotal' => '₱4,599.00',
            'shipping_method' => 'Weight Based',
            'shipping_cost' => '₱150.00',
            'shipping_address' => 'John Doe<br>123 Sample Street<br>Makati City<br>Metro Manila 1200<br>Philippines<br>Phone: 09171234567',
            'payment_method' => 'Bank Transfer',
            'comment' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 12px 0; background-color: #222222; border-radius: 6px;"><tr><td style="padding: 16px 20px;"><p style="margin: 0 0 6px 0; font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px;">Your Note</p><p style="margin: 0; font-size: 13px; color: #e8e8e8; line-height: 1.5; font-style: italic;">Please deliver between 9am-5pm. Leave with building guard if not home.</p></td></tr></table>',
            'old_status' => 'Processing',
            'new_status' => 'Shipped',
            'comment' => 'Your order has been handed over to the courier.',
            'tracking_number' => 'PH123456789',
            'carrier_name' => 'J&T Express',
            'store_name' => config('app.name'),
            'store_url' => '#',
            'reset_url' => '#',
            'product_name' => 'Boss DS-1 Distortion Pedal',
            'product_image' => '',
            'review_url' => '#',
            'customer_email' => 'john@example.com',
            'product_sku' => 'BOSS-DS1',
            'current_qty' => '2',
        ];
    }

    public function index()
    {
        $templates = EmailTemplate::where('slug', 'not like', 'abandoned_cart_%')
            ->orderBy('type')->orderBy('name')->get();

        return view('admin.email-templates.index', compact('templates'));
    }

    public function edit(EmailTemplate $emailTemplate)
    {
        $placeholders = self::PLACEHOLDER_MAP[$emailTemplate->slug] ?? [];

        return view('admin.email-templates.edit', compact('emailTemplate', 'placeholders'));
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $emailTemplate->update($validated);

        return redirect()->route('admin.email-templates.edit', $emailTemplate)
            ->with('success', 'Email template updated successfully.');
    }

    public function toggleActive(EmailTemplate $emailTemplate)
    {
        $emailTemplate->update(['is_active' => !$emailTemplate->is_active]);

        $status = $emailTemplate->is_active ? 'enabled' : 'disabled';

        return back()->with('success', "Template \"{$emailTemplate->name}\" {$status}.");
    }

    public function sendTest(EmailTemplate $emailTemplate)
    {
        $user = Auth::user();
        $result = TemplateMailService::send($emailTemplate->slug, $user->email, self::testData());

        if ($result) {
            return back()->with('success', "Test email sent to {$user->email}.");
        }

        return back()->with('error', 'Failed to send test email. Check the logs for details.');
    }
}
