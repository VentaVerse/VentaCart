<?php

namespace App\Services\Address;

/**
 * Reads deep-address reference data (e.g. PH city/barangay) from bundled JSON
 * files on demand. The data is intentionally NOT in the database — it is read
 * and filtered per request from database/data/<path>/ as declared in
 * config('localization.deep_address').
 *
 * Whole files are decoded then filtered in PHP. At these stores' scale this is
 * well under perceptible latency; files are memoised per request so a cascade
 * that touches the same file twice only decodes it once.
 */
class DeepAddressData
{
    /** @var array<string, array> in-request memo of decoded files */
    private array $cache = [];

    /**
     * Return the options for one cascade level, optionally filtered by parent id.
     *
     * @return array<int, array{id: mixed, name: string, code: ?string, zip_code?: ?string}>
     */
    public function level(string $countryIso, string $levelKey, ?string $parentId = null): array
    {
        $config = config('localization.deep_address.' . strtoupper($countryIso));
        if (!$config) {
            return [];
        }

        $level = collect($config['levels'])->firstWhere('key', $levelKey);
        if (!$level) {
            return [];
        }

        $rows = $this->read($config['path'], $level['file']);

        // Dependent level — keep rows whose stored parent code matches the
        // selected parent. The cleaned files carry an explicit `parent` code per
        // row, so this is an exact match (no prefix/id guessing).
        if (!empty($level['parent'])) {
            if ($parentId === null || $parentId === '') {
                return [];
            }
            $rows = array_filter($rows, fn ($r) => (string) ($r['parent'] ?? '') === (string) $parentId);
        }

        $items = [];
        foreach ($rows as $r) {
            // Cast code to string so leading-zero codes ("012800000") survive
            // alongside ones the source stored as ints (140100000).
            $code = (string) ($r['code'] ?? '');
            $item = [
                'id'   => $code,
                'name' => $r['name'],
                'code' => $code,
            ];
            if (array_key_exists('zip_code', $r)) {
                $item['zip_code'] = $r['zip_code'];
            }
            $items[] = $item;
        }

        usort($items, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return $items;
    }

    private function read(string $path, string $file): array
    {
        $key = $path . '/' . $file;
        if (!isset($this->cache[$key])) {
            $full = database_path('data/' . $key);
            $this->cache[$key] = is_file($full)
                ? (json_decode(file_get_contents($full), true) ?: [])
                : [];
        }

        return $this->cache[$key];
    }
}
