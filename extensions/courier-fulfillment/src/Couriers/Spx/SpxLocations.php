<?php

namespace Extensions\CourierFulfillment\Couriers\Spx;

/**
 * SPX's own location list (database/data/spx-locations.json in this
 * extension, compiled from SPX's location spreadsheet by
 * `courier:spx-locations-compile`) and the mapping from a stored PSGC
 * address onto it.
 *
 * SPX's four levels are not PSGC's: `state` is an island-group bucket
 * (Metro Manila, North Luzon, South Luzon, Visayas, Mindanao), `city` is the
 * province ("Metro Manila" for NCR), `district` is the city or municipality
 * (for the City of Manila: its sub-district, "Ermita"), `street` is the
 * barangay. SPX rejects any other combination as an unsupported address, so
 * an address is only sent as a row that exists in the list, never guessed.
 *
 * Names are matched on a key: lowercase, accents and ñ folded, "City of" /
 * "City" and punctuation dropped, common abbreviations spelled out, and a
 * trailing parenthetical ("(Capital)", "(Pob.)") ignored. Where PSGC and SPX
 * spell a province or municipality differently the aliases below say so;
 * they come from comparing every PSGC province and city with the SPX list.
 *
 * The index is compiled once to storage/framework/cache/spx, named by the
 * data's and this class's modification times, so a newer list or a change
 * to key() rebuilds it.
 */
class SpxLocations
{
    /** PSGC province key => SPX province key. */
    private const PROVINCE_ALIASES = [
        'samar' => 'westernsamar',
        'davaooccidental' => 'davaodelsur',
    ];

    /** "PSGC province key|PSGC city key" => SPX district key, for municipalities SPX spells differently. */
    private const CITY_ALIASES = [
        'catanduanes|bagamanoc' => 'bayamanoc',
        'maguindanao|barira' => 'barrira',
        'cebu|cordova' => 'cordoba',
        'bohol|getafe' => 'jetafe',
        'nuevavizcaya|kasibu' => 'kasiba',
        'abra|lagangilang' => 'lagangilan',
        'easternsamar|lawaan' => 'lauaan',
        'sulu|lugus' => 'lungus',
        'misamisoccidental|ozamiz' => 'ozamis',
        'abra|licuanbaay' => 'licuan',
    ];

    private const ABBREVIATIONS = [
        'sto' => 'santo', 'sta' => 'santa', 'pob' => 'poblacion', 'brgy' => 'barangay', 'bgy' => 'barangay',
        'gen' => 'general', 'pres' => 'president', 'bry' => 'barangay', 'hen' => 'general',
    ];

    /** The City of Manila's sub-districts as SPX keys them. */
    private const MANILA_DISTRICTS = [
        'binondo', 'ermita', 'intramuros', 'malate', 'paco', 'pandacan', 'portarea', 'quiapo', 'sampaloc',
        'sanmiguel', 'sannicolas', 'santaana', 'santacruz', 'tondoiii',
    ];

    /** SPX flag positions in each row's flag string (the spreadsheet's column order). */
    public const PICKUP = 0;

    public const COD_PICKUP = 1;

    public const DELIVER = 2;

    public const COD_DELIVER = 3;

    /** @var array<string, array<string, mixed>> */
    private static array $memo = [];

    private readonly string $path;

    private readonly string $compiledDir;

    private readonly string $psgcDir;

    public function __construct(?string $path = null, ?string $compiledDir = null, ?string $psgcDir = null)
    {
        $this->path = $path ?? self::defaultPath();
        $this->compiledDir = $compiledDir ?? storage_path('framework/cache/spx');
        $this->psgcDir = $psgcDir ?? database_path('data/ph');
    }

    /** Where the compiled list ships: inside this extension, so a fresh install has it. */
    public static function defaultPath(): string
    {
        return dirname(__DIR__, 3) . '/database/data/spx-locations.json';
    }

    /**
     * The SPX location for a PSGC address (state = province, city, district
     * = Manila sub-district, barangay).
     *
     * @param array{state?: ?string, city?: ?string, district?: ?string, barangay?: ?string} $address
     * @return array{ok: true, location: array{state: string, city: string, district: string, street: string}, flags: string}|array{ok: false, missing: 'province'|'city'|'barangay'}
     */
    public function resolve(array $address): array
    {
        $index = $this->index();
        $province = self::key((string) ($address['state'] ?? ''));
        $province = self::PROVINCE_ALIASES[$province] ?? $province;
        if ($province === '' || !isset($index['provinces'][$province])) {
            return ['ok' => false, 'missing' => 'province'];
        }

        $districts = $this->districtKeys($index, $province, (string) ($address['city'] ?? ''), (string) ($address['district'] ?? ''));
        if ($districts === []) {
            return ['ok' => false, 'missing' => 'city'];
        }

        $barangay = (string) ($address['barangay'] ?? '');
        $found = [];
        $siblings = null;
        foreach ($districts as $district) {
            $looseKey = null;
            $street = $this->street($index['streets'][$province . '|' . $district] ?? [], $barangay, $looseKey);
            if ($street === null) {
                continue;
            }
            // A loose match ("San Jose (Pob.)" as SPX's "San Jose") stands
            // only when no other barangay of the same PSGC city would reduce
            // to it too ("Balogo (Bacon District)" / "Balogo (Sorsogon East
            // District)").
            if ($looseKey !== null) {
                $siblings ??= $this->siblings($address);
                if ($siblings === null || array_filter($siblings, fn ($n) => $n !== trim($barangay) && in_array($looseKey, self::streetKeys($n), true)) !== []) {
                    continue;
                }
            }
            $found[] = [$district, $street];
        }
        // A Manila address without its sub-district is placed only when the
        // barangay names one place.
        if (count($found) !== 1) {
            return ['ok' => false, 'missing' => 'barangay'];
        }
        [$district, [$street, $flags]] = $found[0];

        return ['ok' => true, 'location' => [
            'state' => $index['provinces'][$province]['state'],
            'city' => $index['provinces'][$province]['city'],
            'district' => $index['districts'][$province][$district],
            'street' => $street,
        ], 'flags' => $flags];
    }

    public static function supports(string $flags, int $flag): bool
    {
        return ($flags[$flag] ?? '0') === '1';
    }

    /**
     * The match key for a name: folded to lowercase ASCII letters and digits,
     * with no spaces or punctuation. $place also drops "City of" / "City" and
     * a trailing parenthetical, for province and city names.
     */
    public static function key(string $name, bool $place = true): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['ñ', 'Ñ'], 'n', $name);
        $folded = class_exists(\Normalizer::class) ? \Normalizer::normalize($name, \Normalizer::FORM_D) : false;
        $name = (string) preg_replace('/\p{Mn}/u', '', $folded === false ? $name : $folded);
        $name = str_replace(["'", '’', '`'], '', $name);
        if ($place) {
            $name = (string) preg_replace('/(\s*\([^)]*\))+\s*$/', '', $name);
        }
        $tokens = preg_split('/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_map(fn ($t) => self::ABBREVIATIONS[$t] ?? $t, $tokens);
        // "Bgy. No. 5" is "Barangay 5".
        foreach ($tokens as $i => $t) {
            if ($t === 'no' && ($tokens[$i - 1] ?? null) === 'barangay') {
                unset($tokens[$i]);
            }
        }
        $tokens = array_values($tokens);
        if ($place) {
            if (count($tokens) > 2 && $tokens[0] === 'city' && $tokens[1] === 'of') {
                $tokens = array_slice($tokens, 2);
            }
            if (count($tokens) > 1 && end($tokens) === 'city') {
                array_pop($tokens);
            }
        }

        return implode('', $tokens);
    }

    /** @return list<string> The SPX district keys a PSGC city (and Manila sub-district) can be. */
    private function districtKeys(array $index, string $province, string $city, string $district): array
    {
        $known = $index['districts'][$province];
        $cityKey = self::key($city);
        if ($cityKey === '') {
            return [];
        }
        // NCR: SPX lists Manila by sub-district and every other city by its own name.
        if ($province === 'metromanila' && $cityKey === 'manila') {
            if (trim($district) !== '') {
                $key = self::key($district);

                return isset($known[$key]) ? [$key] : [];
            }

            return array_values(array_intersect(array_keys($known), self::MANILA_DISTRICTS));
        }
        foreach ([self::CITY_ALIASES[$province . '|' . $cityKey] ?? null, $cityKey] as $key) {
            if ($key !== null && isset($known[$key])) {
                return [$key];
            }
        }

        return [];
    }

    /**
     * The SPX street for a barangay: by its full key, then without a trailing
     * parenthetical ("Barangay 1 (Pob.)"). Several SPX spellings of one key
     * are the same place: an exact spelling wins, else the first.
     *
     * $looseKey is set to the key used when it was not the barangay's full name.
     *
     * @param array<string, list<array{0: string, 1: string}>> $streets
     * @return array{0: string, 1: string}|null
     */
    private function street(array $streets, string $barangay, ?string &$looseKey = null): ?array
    {
        $looseKey = null;
        $full = self::key($barangay, false);
        foreach (array_unique([$full, ...self::streetKeys($barangay)]) as $key) {
            if ($key === '' || !isset($streets[$key])) {
                continue;
            }
            $looseKey = $key === $full ? null : $key;
            foreach ($streets[$key] as $row) {
                if (mb_strtolower($row[0]) === mb_strtolower(trim($barangay))) {
                    return $row;
                }
            }

            return $streets[$key][0];
        }

        return null;
    }

    /**
     * The looser keys a barangay name can be listed under: without its
     * trailing parenthetical, and for "Bgy. No. 1, San Lorenzo (Pob.)" its
     * number alone, which is how SPX lists some numbered barangays.
     *
     * @return list<string>
     */
    private static function streetKeys(string $barangay): array
    {
        $keys = [self::key($barangay)];
        if (preg_match('/^([^,]*\d[\s-]*[a-z]?)\s*,/iu', trim($barangay), $m)) {
            $keys[] = self::key($m[1]);
        }

        return $keys;
    }

    /**
     * The barangay names of the address's PSGC city (or Manila district),
     * from core's own PSGC data; null when the address cannot be placed there.
     *
     * @param array<string, mixed> $address
     * @return list<string>|null
     */
    private function siblings(array $address): ?array
    {
        $psgc = $this->psgcIndex();
        $province = self::key((string) ($address['state'] ?? ''));
        $city = self::key((string) ($address['city'] ?? ''));
        $district = self::key((string) ($address['district'] ?? ''));
        $names = $psgc[$province . '|' . $city . ($district !== '' ? '|' . $district : '')] ?? null;

        return is_array($names) && $names !== [] ? $names : null;
    }

    /** @return array<string, mixed> */
    private function index(): array
    {
        return $this->compiled('locations', $this->path, fn () => $this->build());
    }

    /** @return array<string, list<string>> "province|city[|district]" key => PSGC barangay names */
    private function psgcIndex(): array
    {
        return $this->compiled('psgc', $this->psgcDir . '/barangays.json', fn () => $this->buildPsgc());
    }

    /**
     * A built index, from memory, from its compiled PHP file, or built and
     * written. The file is named by the source's and this class's
     * modification times; an older one is removed once a newer one exists.
     *
     * @param \Closure(): array<string, mixed> $build
     * @return array<string, mixed>
     */
    private function compiled(string $name, string $source, \Closure $build): array
    {
        $stamp = (is_file($source) ? (string) filemtime($source) : '0') . '-' . (string) filemtime(__FILE__) . '-' . substr(md5($source), 0, 8);
        $compiled = $this->compiledDir . "/{$name}-{$stamp}.php";

        if (isset(self::$memo[$compiled])) {
            return self::$memo[$compiled];
        }
        if (is_file($compiled)) {
            return self::$memo[$compiled] = require $compiled;
        }

        $index = $build();
        if (!is_dir($this->compiledDir)) {
            @mkdir($this->compiledDir, 0775, true);
        }
        $tmp = $compiled . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, '<?php return ' . var_export($index, true) . ';' . PHP_EOL) !== false && @rename($tmp, $compiled)) {
            foreach (glob($this->compiledDir . "/{$name}-*.php") ?: [] as $stale) {
                if ($stale !== $compiled) {
                    @unlink($stale);
                }
            }
        } else {
            @unlink($tmp);
        }

        return self::$memo[$compiled] = $index;
    }

    private function build(): array
    {
        $index = ['provinces' => [], 'districts' => [], 'streets' => []];
        if (!is_file($this->path)) {
            return $index;
        }
        foreach (json_decode((string) file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR) as $row) {
            if (!is_array($row) || count($row) < 5) {
                continue;
            }
            [$state, $city, $district, $street, $flags] = $row;
            $p = self::key((string) $city);
            $d = self::key((string) $district);
            $index['provinces'][$p] ??= ['state' => (string) $state, 'city' => (string) $city];
            $index['districts'][$p][$d] ??= (string) $district;
            $index['streets'][$p . '|' . $d][self::key((string) $street, false)][] = [(string) $street, (string) $flags];
        }

        return $index;
    }

    private function buildPsgc(): array
    {
        $read = function (string $file): array {
            $path = $this->psgcDir . '/' . $file;

            return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        };

        $provinces = [];
        foreach ($read('provinces.json') as $row) {
            $provinces[(string) $row['code']] = self::key((string) $row['name']);
        }
        $parents = [];
        foreach ($read('cities.json') as $row) {
            $province = $provinces[(string) $row['parent']] ?? null;
            if ($province !== null) {
                $parents[(string) $row['code']] = $province . '|' . self::key((string) $row['name']);
            }
        }
        foreach ($read('districts.json') as $row) {
            $city = $parents[(string) $row['parent']] ?? null;
            if ($city !== null) {
                $parents[(string) $row['code']] = $city . '|' . self::key((string) $row['name']);
            }
        }

        $index = [];
        foreach ($read('barangays.json') as $row) {
            $parent = $parents[(string) $row['parent']] ?? null;
            if ($parent !== null) {
                $index[$parent][] = (string) $row['name'];
            }
        }

        return $index;
    }
}
