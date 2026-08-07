<?php

namespace App\Http\Controllers\Admin\Marketing\Seo;

use App\Http\Controllers\Controller;
use App\Models\NotFoundLog;
use Illuminate\Http\Request;

class SeoNotFoundController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $sort = $request->input('sort', 'hits');

        $logs = NotFoundLog::query()
            ->when($search !== '', fn ($q) => $q->where('path', 'like', "%{$search}%"))
            ->when($sort === 'recent', fn ($q) => $q->orderByDesc('last_hit_at'))
            ->when($sort === 'first', fn ($q) => $q->orderBy('first_hit_at'))
            ->when(! in_array($sort, ['recent', 'first'], true), fn ($q) => $q->orderByDesc('hits')->orderByDesc('last_hit_at'))
            ->paginate(50)
            ->withQueryString();

        $totalLogged = NotFoundLog::count();
        $totalHits = (int) NotFoundLog::sum('hits');

        return view('admin.marketing.seo.not-found', compact('logs', 'search', 'sort', 'totalLogged', 'totalHits'));
    }

    public function destroy(NotFoundLog $log)
    {
        $log->delete();

        return redirect()->route('admin.extensions.seo.not-found.index')
            ->with('success', 'Entry removed.');
    }

    public function clear(Request $request)
    {
        $request->validate([
            'older_than_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        if ($days = $request->input('older_than_days')) {
            $cutoff = now()->subDays((int) $days);
            $deleted = NotFoundLog::where('last_hit_at', '<', $cutoff)->delete();
            $msg = "Cleared {$deleted} entries older than {$days} days.";
        } else {
            $deleted = NotFoundLog::query()->delete();
            $msg = "Cleared all {$deleted} entries.";
        }

        return redirect()->route('admin.extensions.seo.not-found.index')->with('success', $msg);
    }
}
