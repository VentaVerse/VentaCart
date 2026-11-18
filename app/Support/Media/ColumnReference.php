<?php

namespace App\Support\Media;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A database column that holds library image paths, either as the whole
 * value (`path`) or somewhere inside a JSON document (`json`).
 *
 * SQL only narrows the rows: MySQL's usual collations compare without regard
 * to case or trailing spaces, and LIKE treats `_` and `%` as wildcards. Every
 * candidate is then checked in PHP with an exact comparison, and every write
 * goes by primary key after that check.
 */
final class ColumnReference implements MediaReferenceSource
{
    /** Rows read at a time from a JSON column, which has to be read whole. */
    private const CHUNK = 200;

    /** Paths per IN (...) list, well under every driver's bound-parameter limit. */
    private const IN_CHUNK = 500;

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    private function __construct(
        private readonly string $table,
        private readonly string $column,
        private readonly string $label,
        private readonly ?string $owner,
        private readonly bool $countsAsUse,
        private readonly bool $isJson,
    ) {}

    /**
     * A column whose whole value is a path.
     *
     * With $owner, the owner key is "{label}:{owner value}", so two columns
     * that belong to one record (a product's main image and its gallery rows)
     * count that record once. Without it, the key is "{table}:{id}".
     */
    public static function path(string $table, string $column, string $label, ?string $owner = null, bool $countsAsUse = true): self
    {
        return new self($table, $column, $label, $owner, $countsAsUse, false);
    }

    /**
     * A column holding JSON, any string of which may be a path. A value that
     * is not JSON is compared whole. Every row is read, so use this only for
     * small tables.
     */
    public static function json(string $table, string $column, string $label, bool $countsAsUse = true): self
    {
        return new self($table, $column, $label, null, $countsAsUse, true);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function countsAsUse(): bool
    {
        return $this->countsAsUse;
    }

    public function owners(array $paths): array
    {
        $paths = array_values(array_unique(array_filter($paths, fn ($p) => is_string($p) && $p !== '')));

        if ($paths === [] || ! Schema::hasTable($this->table)) {
            return [];
        }

        return $this->isJson ? $this->jsonOwners($paths) : $this->pathOwners($paths);
    }

    public function repoint(string $from, string $to): int
    {
        if ($from === '' || $from === $to || ! Schema::hasTable($this->table)) {
            return 0;
        }

        return $this->isJson ? $this->jsonRepoint($from, $to) : $this->pathRepoint($from, $to);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<string, array<int, string>>
     */
    private function pathOwners(array $paths): array
    {
        $wanted = array_flip($paths);
        $columns = array_values(array_unique(array_filter(['id', $this->column, $this->owner])));
        $out = [];

        foreach (array_chunk($paths, self::IN_CHUNK) as $chunk) {
            $rows = DB::table($this->table)->whereIn($this->column, $chunk)->get($columns);

            foreach ($rows as $row) {
                $value = $row->{$this->column};

                if (! is_string($value) || ! isset($wanted[$value])) {
                    continue;
                }

                $key = $this->owner !== null
                    ? $this->label . ':' . $row->{$this->owner}
                    : $this->table . ':' . $row->id;

                $out[$value][$key] = true;
            }
        }

        return array_map(fn (array $keys) => array_keys($keys), $out);
    }

    private function pathRepoint(string $from, string $to): int
    {
        $changed = 0;

        $this->table()
            ->where(function (Builder $q) use ($from) {
                $q->where($this->column, $from)
                    ->orWhereRaw(DB::getQueryGrammar()->wrap($this->column) . ' LIKE ? ESCAPE ?', [self::escapeLike($from) . '/%', '\\']);
            })
            ->select(['id', $this->column])
            ->chunkById(self::CHUNK, function ($rows) use ($from, $to, &$changed) {
                foreach ($rows as $row) {
                    $value = $row->{$this->column};
                    $moved = is_string($value) ? self::moved($value, $from, $to) : null;

                    if ($moved !== null) {
                        $changed += DB::table($this->table)->where('id', $row->id)->update([$this->column => $moved]);
                    }
                }
            });

        return $changed;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<string, array<int, string>>
     */
    private function jsonOwners(array $paths): array
    {
        $wanted = array_flip($paths);
        $out = [];

        $this->table()->select(['id', $this->column])->chunkById(self::CHUNK, function ($rows) use ($wanted, &$out) {
            foreach ($rows as $row) {
                foreach (self::strings(self::decode($row->{$this->column})) as $value) {
                    if (isset($wanted[$value])) {
                        $out[$value][$this->table . ':' . $row->id] = true;
                    }
                }
            }
        });

        return array_map(fn (array $keys) => array_keys($keys), $out);
    }

    private function jsonRepoint(string $from, string $to): int
    {
        $changed = 0;

        $this->table()->select(['id', $this->column])->chunkById(self::CHUNK, function ($rows) use ($from, $to, &$changed) {
            foreach ($rows as $row) {
                $raw = $row->{$this->column};
                [$isJson, $decoded] = self::decodeWithKind($raw);
                $hit = false;
                $rewritten = self::rewrite($decoded, $from, $to, $hit);

                if (! $hit) {
                    continue;
                }

                $stored = $isJson ? json_encode($rewritten, self::JSON_FLAGS) : $rewritten;

                if ($stored === false) {
                    continue;
                }

                $changed += DB::table($this->table)->where('id', $row->id)->update([$this->column => $stored]);
            }
        });

        return $changed;
    }

    private function table(): Builder
    {
        $query = DB::table($this->table)->whereNotNull($this->column);

        // An empty string is no path. A JSON column is left to PHP: comparing
        // one with '' means different things to different databases.
        return $this->isJson ? $query : $query->where($this->column, '!=', '');
    }

    /** The new value for one stored path, or null when it is not $from or inside it. */
    private static function moved(string $value, string $from, string $to): ?string
    {
        if ($value === $from) {
            return $to;
        }

        if (str_starts_with($value, $from . '/')) {
            return $to . substr($value, strlen($from));
        }

        return null;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function decode(mixed $raw): mixed
    {
        return self::decodeWithKind($raw)[1];
    }

    /**
     * Decode a stored value. Objects stay objects so an empty `{}` is written
     * back as `{}`, not `[]`.
     *
     * @return array{0: bool, 1: mixed} [whether it was JSON, the value]
     */
    private static function decodeWithKind(mixed $raw): array
    {
        if (! is_string($raw)) {
            return [false, $raw];
        }

        try {
            return [true, json_decode($raw, false, 512, JSON_THROW_ON_ERROR)];
        } catch (\JsonException) {
            return [false, $raw];
        }
    }

    /** @return \Generator<int, string> every string in a decoded value */
    private static function strings(mixed $value): \Generator
    {
        if (is_string($value)) {
            yield $value;
        } elseif (is_array($value) || $value instanceof \stdClass) {
            foreach ((array) $value as $item) {
                yield from self::strings($item);
            }
        }
    }

    private static function rewrite(mixed $value, string $from, string $to, bool &$hit): mixed
    {
        if (is_string($value)) {
            $moved = self::moved($value, $from, $to);

            if ($moved === null) {
                return $value;
            }

            $hit = true;

            return $moved;
        }

        if (is_array($value)) {
            foreach ($value as $k => $item) {
                $value[$k] = self::rewrite($item, $from, $to, $hit);
            }
        } elseif ($value instanceof \stdClass) {
            foreach (get_object_vars($value) as $k => $item) {
                $value->{$k} = self::rewrite($item, $from, $to, $hit);
            }
        }

        return $value;
    }
}
