<?php

namespace Themes\Xelga\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NavMenu;
use App\Models\NavMenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Themes\Xelga\Http\Pickers\RecordSearch;
use Themes\Xelga\Http\Requests\PickerRequest;

/**
 * Read-only lookups for the editor's pickers: 20 results a call, each with
 * only what the picker shows. Images use core's media library routes
 * directly. Answers {kind, results, more} (and `available: false` for the
 * blog pickers on a store without the blog).
 */
class PickerController extends Controller
{
    use RespondsWithJson;

    private const RECORD_KINDS = ['products', 'categories', 'brands', 'pages', 'blog-posts', 'blog-categories'];

    public function __construct(private readonly RecordSearch $records)
    {
    }

    public function pick(PickerRequest $request, string $kind): JsonResponse
    {
        if ($kind === 'menus') {
            return $this->menus($request->search());
        }

        if ($kind === 'links') {
            return $this->ok(['kind' => 'links'] + $this->records->links($request->search(), $request->ids(typed: true)), 'Loaded.');
        }

        if (! in_array($kind, self::RECORD_KINDS, true)) {
            return $this->fail('That picker is not available.', 'not_found', 404);
        }

        $answer = ['kind' => $kind] + $this->records->search($kind, $request->search(), $request->ids());
        if (str_starts_with($kind, 'blog-')) {
            $answer['available'] = RecordSearch::hasBlog();
        }

        return $this->ok($answer, 'Loaded.');
    }

    private function menus(string $q): JsonResponse
    {
        $counts = NavMenuItem::query()->where('is_enabled', true)->whereNull('parent_id')
            ->selectRaw('menu_id, count(*) as n')->groupBy('menu_id')->pluck('n', 'menu_id');

        $menus = NavMenu::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%' . addcslashes($q, '%_\\') . '%'))
            ->orderBy('name')->limit(50)->get(['id', 'key', 'name'])
            ->map(fn (NavMenu $m) => ['value' => (string) $m->key, 'label' => (string) $m->name, 'items' => (int) ($counts[$m->id] ?? 0)])
            ->values()->all();

        return $this->ok([
            'kind' => 'menus',
            'results' => $menus,
            'edit_url' => Route::has('admin.menus') ? route('admin.menus') : null,
        ], 'Loaded.');
    }
}
