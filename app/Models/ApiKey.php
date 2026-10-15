<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One API key, permitted only the areas it was given.
 *
 * The secret is never stored. Only its hash is kept, so a key that is lost is
 * gone: there is nothing to reveal and nothing a database leak could hand
 * over. The prefix exists purely so a person can tell two keys apart.
 */
class ApiKey extends Model
{
    protected $fillable = ['label', 'prefix', 'key_hash', 'abilities', 'last_used_at'];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['key_hash'];

    /** How many characters of a key are shown in the list. */
    public const PREFIX_LENGTH = 12;

    /**
     * Mint a key and return it ONCE, alongside the saved record.
     *
     * @param  array<string, list<string>>  $abilities
     * @return array{0: self, 1: string}
     */
    public static function issue(string $label, array $abilities): array
    {
        $secret = 'vnt_' . Str::random(48);

        $key = self::create([
            'label' => $label,
            'prefix' => substr($secret, 0, self::PREFIX_LENGTH),
            'key_hash' => self::hashSecret($secret),
            'abilities' => self::clean($abilities),
        ]);

        return [$key, $secret];
    }

    /**
     * The key this secret belongs to, or null.
     *
     * Looked up by prefix so the database does the narrowing, then confirmed
     * with a timing-safe comparison of the full hash. The prefix alone proves
     * nothing.
     */
    public static function findBySecret(string $secret): ?self
    {
        $candidate = self::where('prefix', substr($secret, 0, self::PREFIX_LENGTH))->first();

        if (! $candidate || ! hash_equals($candidate->key_hash, self::hashSecret($secret))) {
            return null;
        }

        return $candidate;
    }

    public function allows(string $area, string $mode): bool
    {
        return in_array($mode, $this->abilities[$area] ?? [], true);
    }

    /** Only areas and modes that exist, so a typo cannot grant anything. */
    public static function clean(array $abilities): array
    {
        $clean = [];

        foreach (\App\Support\ApiAbility::AREAS as $area) {
            $modes = array_values(array_intersect(
                \App\Support\ApiAbility::MODES,
                (array) ($abilities[$area] ?? [])
            ));

            if ($modes !== []) {
                $clean[$area] = $modes;
            }
        }

        return $clean;
    }

    public static function hashSecret(string $secret): string
    {
        // sha256, not bcrypt: this is verified on every API request and must
        // stay fast, and the secret is 48 random characters rather than a
        // human password, so there is nothing to brute-force.
        return hash('sha256', $secret);
    }
}
