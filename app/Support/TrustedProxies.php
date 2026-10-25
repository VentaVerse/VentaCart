<?php

namespace App\Support;

/**
 * Reads the TRUSTED_PROXIES setting (config/trustedproxy.php) into the
 * shape Laravel's TrustProxies middleware takes.
 */
final class TrustedProxies
{
    /**
     * Null when nothing is trusted, '*' to trust whoever connects, or a list
     * of addresses, ranges and the word private_ranges.
     *
     * @return list<string>|string|null
     */
    public static function parse(mixed $value): array|string|null
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || in_array(strtolower($value), ['null', 'none', 'false'], true)) {
            return null;
        }
        if ($value === '*' || $value === '**') {
            return '*';
        }

        $list = [];
        foreach (explode(',', $value) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            $list[] = in_array(strtolower($entry), ['private_ranges', 'private_subnets'], true) ? 'PRIVATE_SUBNETS' : $entry;
        }

        return $list === [] ? null : array_values(array_unique($list));
    }
}
