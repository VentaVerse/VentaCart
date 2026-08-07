<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ApiSyncLog;
use Illuminate\Http\Request;

class ApiSyncLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ApiSyncLog::query()->orderBy('created_at', 'desc');

        if ($request->filled('method')) {
            $query->where('method', $request->input('method'));
        }

        if ($request->filled('status')) {
            $query->where('status_code', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('endpoint', 'like', '%' . $search . '%')
                  ->orWhere('sku', 'like', '%' . $search . '%');
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('admin.settings.api-sync-logs.index', compact('logs'));
    }

    public function destroyAll()
    {
        $count = ApiSyncLog::count();
        ApiSyncLog::truncate();

        return redirect()->route('admin.api-sync-logs.index')
            ->with('success', "{$count} log(s) deleted.");
    }
}
