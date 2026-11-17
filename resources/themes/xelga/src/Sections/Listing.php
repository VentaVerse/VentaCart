<?php

namespace Themes\Xelga\Sections;

use App\Services\ThemeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Themes\Xelga\Rendering\RenderContext;

/**
 * What the listing pages (category, brand, search) share: the cards for the
 * page of products core's controller chose, the pages around it, the sort
 * choices core's controller accepts, the filters it understands and the
 * filters the shopper has on.
 *
 * Nothing here queries products. The controller already applied the
 * filters, the sort and the customer-group visibility; this class only
 * reads its paginator and the request the controller read.
 */
final class Listing
{
    /** The sort keys core's category and brand controllers accept, with their labels. */
    public const SORTS = [
        'date_added' => 'Newest first',
        'price_asc' => 'Price, low to high',
        'price_desc' => 'Price, high to low',
        'name_asc' => 'Name, A to Z',
        'name_desc' => 'Name, Z to A',
    ];

    /** Search adds a best-match order, which is core's default there. */
    public const SEARCH_SORTS = ['relevance' => 'Best match'] + self::SORTS;

    /** Core's own key for "most recently created"; offered only when a link already uses it. */
    private const CREATED = ['newest' => 'Recently added'];

    /** Request parameters the filter form owns; everything else in the address is kept. */
    public const FILTER_KEYS = ['min_price', 'max_price', 'brand', 'in_stock', 'on_sale', 'tag', 'attr'];

    /**
     * The cards and pages of the controller's paginator.
     *
     * @return array<string, mixed>
     */
    public static function page(mixed $products, RenderContext $context, string $noun = 'product'): array
    {
        if (! $products instanceof LengthAwarePaginator) {
            return ['cards' => [], 'total' => 0, 'from' => 0, 'to' => 0, 'current' => 1, 'last' => 1, 'prev' => null, 'next' => null, 'pages' => [], 'count_text' => '0 ' . $noun . 's'];
        }

        $total = (int) $products->total();

        return [
            'cards' => ProductCards::for($products->items(), Swatches::wanted($context), CardLines::wanted($context)),
            'total' => $total,
            'from' => (int) ($products->firstItem() ?? 0),
            'to' => (int) ($products->lastItem() ?? 0),
            'current' => (int) $products->currentPage(),
            'last' => (int) $products->lastPage(),
            'prev' => $products->previousPageUrl(),
            'next' => $products->nextPageUrl(),
            'pages' => self::window($products),
            'count_text' => $total . ' ' . ($total === 1 ? $noun : $noun . 's'),
        ];
    }

    /**
     * Page numbers to draw: the first, the last and two either side of the
     * current one, with a gap marker (null) where numbers are skipped.
     *
     * @return list<array{number: ?int, url: ?string, current: bool}>
     */
    private static function window(LengthAwarePaginator $products): array
    {
        $last = (int) $products->lastPage();
        $current = (int) $products->currentPage();
        if ($last <= 1) {
            return [];
        }

        $numbers = array_values(array_unique(array_filter(
            [1, $current - 1, $current, $current + 1, $last],
            fn (int $n) => $n >= 1 && $n <= $last,
        )));
        sort($numbers);

        $out = [];
        $previous = 0;
        foreach ($numbers as $n) {
            if ($n - $previous > 1) {
                $out[] = ['number' => null, 'url' => null, 'current' => false];
            }
            $out[] = ['number' => $n, 'url' => $products->url($n), 'current' => $n === $current];
            $previous = $n;
        }

        return $out;
    }

    /**
     * The sort choices, with the one core used selected.
     *
     * @param  array<string, string>  $choices
     * @return list<array{value: string, label: string, selected: bool}>
     */
    public static function sorts(array $choices, string $current): array
    {
        if (! isset($choices[$current]) && isset(self::CREATED[$current])) {
            $choices += self::CREATED;
        }
        $selected = isset($choices[$current]) ? $current : (string) array_key_first($choices);

        $out = [];
        foreach ($choices as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label, 'selected' => $value === $selected];
        }

        return $out;
    }

    /**
     * Which filters the store shows, from core's own Sidebar filters setting
     * (Settings, Catalog). A filter the store has never set is shown, as
     * core's storefront does.
     *
     * @return array{price: bool, availability: bool, on_sale: bool, brand: bool, tags: bool}
     */
    public static function enabledFilters(): array
    {
        $group = app(ThemeService::class)->getGroup('filters');
        $on = static fn (string $key): bool => ! array_key_exists($key, $group) || ! in_array((string) $group[$key], ['0', 'false', ''], true);

        return [
            'price' => $on('price_enabled'),
            'availability' => $on('availability_enabled'),
            'on_sale' => $on('on_sale_enabled'),
            'brand' => $on('brand_enabled'),
            'tags' => $on('tags_enabled'),
        ];
    }

    /**
     * The filter groups for the form, each value marked when the shopper
     * has it on. Only parameters core's controller reads are offered.
     *
     * @param  array{brands?: iterable, tags?: iterable, attributes?: array, price?: mixed, brand_filter?: bool}  $source
     * @return array{facets: list<array>, price: ?array, chips: list<array{label: string, url: string}>, active: int, clear_url: string}
     */
    public static function filters(array $source): array
    {
        $request = request();
        $enabled = self::enabledFilters();
        $facets = [];
        $chips = [];

        $price = null;
        if ($enabled['price']) {
            $range = $source['price'] ?? null;
            $min = self::number($request->query('min_price'));
            $max = self::number($request->query('max_price'));
            $price = [
                'symbol' => (string) (app(ThemeService::class)->getDefaultCurrency()?->symbol ?? ''),
                'min' => $min,
                'max' => $max,
                'min_hint' => is_object($range) && $range->min_price !== null ? number_format((float) $range->min_price, 0) : '',
                'max_hint' => is_object($range) && $range->max_price !== null ? number_format((float) $range->max_price, 0) : '',
            ];
            if ($min !== null || $max !== null) {
                $chips[] = [
                    'label' => ($min !== null ? $price['symbol'] . number_format($min, 0) : $price['symbol'] . '0') . ' to ' . ($max !== null ? $price['symbol'] . number_format($max, 0) : 'any'),
                    'url' => self::without(['min_price' => null, 'max_price' => null]),
                ];
            }
        }

        $avail = [];
        if ($enabled['availability']) {
            $on = $request->filled('in_stock');
            $avail[] = ['name' => 'in_stock', 'value' => '1', 'label' => 'In stock only', 'checked' => $on, 'swatch' => null, 'count' => null];
            if ($on) {
                $chips[] = ['label' => 'In stock only', 'url' => self::without(['in_stock' => null])];
            }
        }
        if ($enabled['on_sale']) {
            $on = $request->filled('on_sale');
            $avail[] = ['name' => 'on_sale', 'value' => '1', 'label' => 'On sale', 'checked' => $on, 'swatch' => null, 'count' => null];
            if ($on) {
                $chips[] = ['label' => 'On sale', 'url' => self::without(['on_sale' => null])];
            }
        }
        if ($avail !== []) {
            $facets[] = ['key' => 'availability', 'label' => 'Availability', 'open' => true, 'items' => $avail];
        }

        if ($enabled['brand'] && ($source['brand_filter'] ?? false)) {
            $chosen = self::ids($request->query('brand'));
            $items = [];
            foreach ($source['brands'] ?? [] as $brand) {
                $checked = in_array((int) $brand->id, $chosen, true);
                $items[] = ['name' => 'brand[]', 'value' => (string) $brand->id, 'label' => (string) $brand->name, 'checked' => $checked, 'swatch' => null, 'count' => null];
                if ($checked) {
                    $chips[] = ['label' => (string) $brand->name, 'url' => self::without(['brand' => (string) $brand->id])];
                }
            }
            if ($items !== []) {
                $facets[] = ['key' => 'brand', 'label' => 'Brand', 'open' => true, 'items' => $items];
            }
        }

        $chosenAttrs = $request->query('attr');
        $chosenAttrs = is_array($chosenAttrs) ? $chosenAttrs : [];
        foreach ((array) ($source['attributes'] ?? []) as $index => $attribute) {
            $slug = (string) ($attribute['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $picked = array_map('strval', array_filter((array) ($chosenAttrs[$slug] ?? []), 'is_scalar'));
            $asSwatches = in_array(mb_strtolower(trim((string) $attribute['name'])), ProductCards::SWATCH_GROUPS, true);
            $items = [];
            foreach ((array) ($attribute['values'] ?? []) as $value) {
                $text = (string) $value['value'];
                $checked = in_array($text, $picked, true);
                $items[] = [
                    'name' => 'attr[' . $slug . '][]',
                    'value' => $text,
                    'label' => $text,
                    'checked' => $checked,
                    'swatch' => $asSwatches ? Swatch::key($text) : null,
                    'count' => (int) ($value['count'] ?? 0),
                ];
                if ($checked) {
                    $chips[] = ['label' => $text, 'url' => self::without(['attr' => [$slug, $text]])];
                }
            }
            if ($items !== []) {
                // The first two groups open; longer lists start closed so the column stays short.
                $facets[] = ['key' => 'attr-' . $slug, 'label' => (string) $attribute['name'], 'open' => $index < 2 || $picked !== [], 'items' => $items];
            }
        }

        if ($enabled['tags']) {
            $chosen = self::ids($request->query('tag'));
            $items = [];
            foreach ($source['tags'] ?? [] as $tag) {
                $checked = in_array((int) $tag->id, $chosen, true);
                $items[] = ['name' => 'tag[]', 'value' => (string) $tag->id, 'label' => (string) $tag->name, 'checked' => $checked, 'swatch' => null, 'count' => null];
                if ($checked) {
                    $chips[] = ['label' => (string) $tag->name, 'url' => self::without(['tag' => (string) $tag->id])];
                }
            }
            if ($items !== []) {
                $facets[] = ['key' => 'tags', 'label' => 'Tags', 'open' => $chosen !== [], 'items' => $items];
            }
        }

        $keep = array_filter(['sort' => $request->query('sort')], fn ($v) => is_string($v) && $v !== '');

        return [
            'facets' => $facets,
            'price' => $price,
            'chips' => $chips,
            'active' => count($chips),
            'clear_url' => $request->url() . ($keep === [] ? '' : '?' . http_build_query($keep)),
        ];
    }

    /**
     * The current address without one filter (or one of its values), and
     * back on the first page, since the list it pointed into changes.
     *
     * @param  array<string, mixed>  $drop  key => null drops the key; key => value drops that value; attr => [slug, value]
     */
    public static function without(array $drop): string
    {
        $request = request();
        $query = $request->query();
        unset($query['page']);

        foreach ($drop as $key => $value) {
            if ($key === 'attr' && is_array($value)) {
                [$slug, $text] = $value;
                $values = array_values(array_filter((array) ($query['attr'][$slug] ?? []), fn ($v) => (string) $v !== $text));
                if ($values === []) {
                    unset($query['attr'][$slug]);
                } else {
                    $query['attr'][$slug] = $values;
                }
                if (empty($query['attr'])) {
                    unset($query['attr']);
                }
                continue;
            }
            if ($value === null || ! is_array($query[$key] ?? null)) {
                unset($query[$key]);
                continue;
            }
            $query[$key] = array_values(array_filter($query[$key], fn ($v) => (string) $v !== (string) $value));
            if ($query[$key] === []) {
                unset($query[$key]);
            }
        }

        return $request->url() . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /**
     * Query parameters to carry through the filter form, so a sort or a
     * search word survives a change of filters.
     *
     * @return array<string, string>
     */
    public static function carried(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $value = request()->query($key);
            if (is_string($value) && $value !== '') {
                $out[$key] = mb_substr($value, 0, 120);
            }
        }

        return $out;
    }

    /**
     * The current address's parameters as hidden form fields, minus the
     * ones the form itself sets, so sorting keeps the filters and the
     * search words on.
     *
     * @param  list<string>  $except
     * @return list<array{name: string, value: string}>
     */
    public static function hiddenFields(array $except): array
    {
        $out = [];
        $walk = function (mixed $value, string $name) use (&$walk, &$out): void {
            if (is_array($value)) {
                foreach ($value as $key => $inner) {
                    $walk($inner, is_int($key) ? $name . '[]' : $name . '[' . $key . ']');
                }

                return;
            }
            if (is_scalar($value) && count($out) < 200) {
                $out[] = ['name' => $name, 'value' => mb_substr((string) $value, 0, 255)];
            }
        };
        foreach (request()->query() as $key => $value) {
            if (! in_array($key, [...$except, 'page'], true) && preg_match('/^[a-z_]{1,40}$/', (string) $key) === 1) {
                $walk($value, (string) $key);
            }
        }

        return $out;
    }

    /** Where the merchant sets products per page, default sort and which filters show, for the editor's note. */
    public static function settingsUrl(): ?string
    {
        if (! Route::has('admin.settings.section')) {
            return null;
        }

        try {
            return route('admin.settings.section', ['section' => 'catalog']);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function number(mixed $value): ?float
    {
        return is_scalar($value) && is_numeric($value) && (float) $value >= 0 ? (float) $value : null;
    }

    /** @return list<int> */
    private static function ids(mixed $value): array
    {
        return array_values(array_map('intval', array_filter((array) $value, fn ($v) => is_scalar($v) && ctype_digit((string) $v))));
    }
}
