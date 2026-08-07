<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Mail\StockNotificationMail;
use App\Models\StockNotification;
use App\Models\StockNotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class StockNotificationController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->input('tab', session('tab', 'waitlist'));
        $search = $request->input('search');

        // Waitlist query
        $waitlistQuery = StockNotification::waiting()
            ->with(['product', 'variant'])
            ->latest();
        if ($search) {
            $waitlistQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }
        $waitlist = $waitlistQuery->paginate(20, ['*'], 'waitlist_page')->withQueryString();

        // Notified query
        $notifiedQuery = StockNotification::notified()
            ->with(['product', 'variant'])
            ->latest('notified_at');
        if ($search) {
            $notifiedQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }
        $notified = $notifiedQuery->paginate(20, ['*'], 'notified_page')->withQueryString();

        // Template
        $template = StockNotificationTemplate::where('store_id', 1)->first();

        // Analytics: most requested products
        $p = config('database.connections.mysql.prefix', '');
        $topProducts = DB::table('stock_notifications')
            ->join('products', 'stock_notifications.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('COUNT(*) as total_requests'),
                DB::raw("SUM(CASE WHEN {$p}stock_notifications.status = 'waiting' THEN 1 ELSE 0 END) as waiting_count")
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_requests')
            ->limit(10)
            ->get();

        // Summary stats
        $totalWaiting = StockNotification::waiting()->count();
        $totalNotified = StockNotification::notified()->count();
        $totalFailed = StockNotification::where('status', 'failed')->count();

        return view('admin.settings.stock-notifications.index', compact(
            'tab', 'search', 'waitlist', 'notified', 'template',
            'topProducts', 'totalWaiting', 'totalNotified', 'totalFailed'
        ));
    }

    public function destroy(StockNotification $stockNotification)
    {
        $stockNotification->delete();
        return back()->with('success', 'Notification removed.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return back()->with('error', 'No items selected.');
        }
        StockNotification::whereIn('id', $ids)->delete();
        return back()->with('success', count($ids) . ' notification(s) removed.');
    }

    public function updateTemplate(Request $request)
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        StockNotificationTemplate::updateOrCreate(
            ['store_id' => 1],
            $validated
        );

        return back()->with('success', 'Email template updated.')->with('tab', 'template');
    }

    public function sendTest(Request $request)
    {
        $template = StockNotificationTemplate::where('store_id', 1)->first();
        if (!$template) {
            return back()->with('error', 'No email template found. Save one first.');
        }

        // Create a fake notification for testing
        $notification = new StockNotification([
            'name' => 'Test Customer',
            'email' => auth()->user()->email,
            'product_id' => \App\Models\Product::first()?->id ?? 0,
        ]);
        $notification->setRelation('product', \App\Models\Product::with('images')->first() ?? new \App\Models\Product(['name' => 'Sample Product', 'slug' => 'sample']));
        $notification->setRelation('variant', null);

        try {
            Mail::to(auth()->user()->email)->send(
                new StockNotificationMail($notification, $template)
            );
            return back()->with('success', 'Test email sent to ' . auth()->user()->email)->with('tab', 'template');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send: ' . $e->getMessage())->with('tab', 'template');
        }
    }
}
