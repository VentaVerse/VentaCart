<?php

namespace App\Http\Controllers\Admin\Layout;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\NavMenu;
use App\Models\NavMenuItem;
use App\Models\Page;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The menu composer: the store's menus as trees the merchant arranges.
 *
 * Core, like the Layout editor: menus are store data the active theme
 * draws. One page per menu; the arrangement (order and nesting) is the
 * page's save scope, an item is its own record with its own form.
 */
class MenuController extends Controller
{
    public function index(Request $request)
    {
        $menus = NavMenu::orderByRaw("CASE WHEN `key` = 'main' THEN 0 ELSE 1 END")->orderBy('name')->get();
        if ($menus->isEmpty()) {
            $menus = collect([NavMenu::main()]);
        }

        $key = (string) $request->query('menu', NavMenu::MAIN);
        $menu = $menus->firstWhere('key', $key) ?? $menus->first();

        $items = $menu->items()->with(['category', 'page'])->get();

        return view('admin.layout.menus.index', [
            'menus' => $menus,
            'menu' => $menu,
            'tree' => $this->tree($items),
            'types' => NavMenuItem::TYPES,
            'system' => NavMenuItem::SYSTEM,
            'categories' => $this->categoryChoices(),
            'pages' => Page::where('is_active', true)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    /** Items as the editor draws them: nested, with a one-line description. */
    private function tree($items): array
    {
        $byParent = $items->groupBy(fn ($i) => $i->parent_id ? (string) $i->parent_id : '');

        $build = function (?int $parentId) use (&$build, $byParent): array {
            $out = [];
            foreach ($byParent->get($parentId ? (string) $parentId : '', collect()) as $item) {
                $out[] = [
                    'id' => $item->id,
                    'label' => $item->label,
                    'type' => $item->type,
                    'kind' => NavMenuItem::TYPES[$item->type] ?? $item->type,
                    'where' => $this->describe($item),
                    'enabled' => (bool) $item->is_enabled,
                    'mega' => ($item->config['mega'] ?? 'no') === 'yes',
                    'accent' => $item->config['accent'] ?? null,
                    'children' => $build((int) $item->id),
                ];
            }

            return $out;
        };

        return $build(null);
    }

    private function describe(NavMenuItem $item): string
    {
        return match ($item->type) {
            'category' => $item->category?->name ?? 'No category',
            'page' => $item->page?->title ?? 'No page',
            'custom' => (string) $item->url,
            'system' => NavMenuItem::SYSTEM[$item->config['page'] ?? '']['label'] ?? 'No page',
            'html' => 'Custom HTML',
            default => '',
        };
    }

    /** Every active category with its depth, for one select. */
    private function categoryChoices(): array
    {
        $all = Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'parent_id']);
        $byParent = $all->groupBy(fn ($c) => $c->parent_id ? (string) $c->parent_id : '');
        $out = [];
        $walk = function (?int $parentId, int $depth) use (&$walk, $byParent, &$out) {
            foreach ($byParent->get($parentId ? (string) $parentId : '', collect()) as $c) {
                $out[] = ['id' => $c->id, 'label' => str_repeat('- ', $depth) . $c->name];
                $walk((int) $c->id, $depth + 1);
            }
        };
        $walk(null, 0);

        return $out;
    }

    public function storeMenu(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $key = Str::slug($data['name']) ?: 'menu';
        $base = $key;
        for ($i = 2; NavMenu::where('key', $key)->exists(); $i++) {
            $key = $base . '-' . $i;
        }
        $menu = NavMenu::create(['key' => $key, 'name' => $data['name']]);
        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.layout.menus', ['menu' => $menu->key])->with('success', $menu->name . ' created.');
    }

    public function destroyMenu(NavMenu $menu)
    {
        if ($menu->key === NavMenu::MAIN) {
            return back()->with('error', 'The main menu is the header; it stays.');
        }
        $menu->delete();
        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.layout.menus')->with('success', $menu->name . ' deleted.');
    }

    /** The arrangement: order and nesting for one menu, written as one save. */
    public function arrange(Request $request, NavMenu $menu)
    {
        $data = json_decode((string) $request->input('payload'), true);
        if (! is_array($data) || ! is_array($data['items'] ?? null)) {
            return back()->with('error', 'The editor sent nothing this save could apply.');
        }

        $ids = $menu->items()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $rows = collect($data['items'])
            ->filter(fn ($r) => is_array($r) && in_array((int) ($r['id'] ?? 0), $ids, true))
            ->values();

        DB::transaction(function () use ($rows, $ids) {
            foreach ($rows as $i => $row) {
                $parent = (int) ($row['parent_id'] ?? 0);
                NavMenuItem::where('id', (int) $row['id'])->update([
                    'parent_id' => $parent && in_array($parent, $ids, true) && $parent !== (int) $row['id'] ? $parent : null,
                    'sort_order' => $i,
                ]);
            }
        });
        app(ThemeService::class)->clearCache();

        return back()->with('success', $menu->name . ' arranged.');
    }

    public function storeItem(Request $request, NavMenu $menu)
    {
        $data = $this->validated($request);
        $data['menu_id'] = $menu->id;
        $data['sort_order'] = ((int) $menu->items()->max('sort_order')) + 1;
        NavMenuItem::create($data);
        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.layout.menus', ['menu' => $menu->key])->with('success', ($data['label'] ?: NavMenuItem::TYPES[$data['type']]) . ' added.');
    }

    public function editItem(NavMenuItem $item)
    {
        return view('admin.layout.menus.item', [
            'item' => $item,
            'menu' => $item->menu,
            'types' => NavMenuItem::TYPES,
            'system' => NavMenuItem::SYSTEM,
            'categories' => $this->categoryChoices(),
            'pages' => Page::where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'parents' => $item->menu->items()->where('id', '!=', $item->id)->whereNull('parent_id')->get(['id', 'label']),
        ]);
    }

    public function updateItem(Request $request, NavMenuItem $item)
    {
        $data = $this->validated($request, $item);
        $item->update($data);
        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.layout.menus', ['menu' => $item->menu->key])->with('success', ($item->label ?: NavMenuItem::TYPES[$item->type]) . ' updated.');
    }

    public function destroyItem(NavMenuItem $item)
    {
        $menu = $item->menu;
        $item->delete();
        app(ThemeService::class)->clearCache();

        return redirect()->route('admin.layout.menus', ['menu' => $menu->key])->with('success', 'Menu item deleted.');
    }

    /** One set of rules for adding and editing: a type says which fields count. */
    private function validated(Request $request, ?NavMenuItem $item = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(NavMenuItem::TYPES))],
            'label' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->input('type') !== 'separator')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id'), Rule::requiredIf(fn () => $request->input('type') === 'category')],
            'page_id' => ['nullable', 'integer', Rule::exists('pages', 'id'), Rule::requiredIf(fn () => $request->input('type') === 'page')],
            'url' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->input('type') === 'custom')],
            'system_page' => ['nullable', Rule::in(array_keys(NavMenuItem::SYSTEM)), Rule::requiredIf(fn () => $request->input('type') === 'system')],
            'html' => ['nullable', 'string', 'max:20000'],
            'dropdown' => ['nullable', Rule::in(['subcategories', 'none'])],
            'depth' => ['nullable', 'integer', 'min:1', 'max:3'],
            'mega' => ['nullable', Rule::in(['yes', 'no'])],
            'brands' => ['nullable', Rule::in(['yes', 'no'])],
            'banner' => ['nullable', 'string', 'max:500'],
            'banner_url' => ['nullable', 'string', 'max:500'],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'new_tab' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $type = $data['type'];
        $config = [];
        if ($type === 'category') {
            $config = [
                'dropdown' => $data['dropdown'] ?? 'subcategories',
                'depth' => (int) ($data['depth'] ?? 2),
                'mega' => $data['mega'] ?? 'no',
                'brands' => $data['brands'] ?? 'no',
                'banner' => str_contains((string) ($data['banner'] ?? ''), '..') ? '' : ltrim((string) ($data['banner'] ?? ''), '/'),
                'banner_url' => (string) ($data['banner_url'] ?? ''),
            ];
        } elseif ($type === 'html') {
            $config = ['html' => (string) ($data['html'] ?? '')];
        } elseif ($type === 'system') {
            $config = ['page' => $data['system_page']];
        }
        if (! empty($data['accent'])) {
            $config['accent'] = strtoupper($data['accent']);
        }

        $parent = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parent && $item && $parent === $item->id) {
            $parent = null;
        }
        if ($parent && $item && ! $item->menu->items()->where('id', $parent)->exists()) {
            $parent = null;
        }

        return [
            'type' => $type,
            'label' => $type === 'separator' ? '' : (string) $data['label'],
            'category_id' => $type === 'category' ? (int) $data['category_id'] : null,
            'page_id' => $type === 'page' ? (int) $data['page_id'] : null,
            'url' => $type === 'custom' ? (string) $data['url'] : null,
            'config' => $config,
            'new_tab' => (bool) ($data['new_tab'] ?? false),
            'is_enabled' => $item ? (bool) ($data['is_enabled'] ?? true) : true,
            'parent_id' => $item ? $parent : null,
        ];
    }
}
