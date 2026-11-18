<?php

namespace Themes\Xelga\Catalog;

/**
 * One setting: its type, default, bounds, and options.
 */
final class SettingSchema
{
    public readonly string $key;

    public readonly string $type;

    public readonly mixed $default;

    public function __construct(public readonly array $raw)
    {
        $this->key = (string) $raw['key'];
        $this->type = (string) $raw['type'];
        $this->default = $raw['default'] ?? null;
    }

    /** @return list<string> the option values listed in the schema itself */
    public function optionValues(): array
    {
        return array_map(fn ($o) => (string) (is_array($o) ? ($o['value'] ?? '') : $o), (array) ($this->raw['options'] ?? []));
    }

    public function optionsSource(): ?string
    {
        $source = $this->raw['options_source'] ?? null;

        return is_string($source) ? $source : null;
    }

    public function min(): float
    {
        return (float) ($this->raw['min'] ?? 0);
    }

    public function max(): float
    {
        return (float) ($this->raw['max'] ?? 0);
    }

    public function step(): float
    {
        $step = (float) ($this->raw['step'] ?? 1);

        return $step > 0 ? $step : 1.0;
    }

    /** Most characters a text value keeps. */
    public function maxLength(): int
    {
        $limit = match ($this->type) {
            'textarea' => 2000,
            'richtext' => 5000,
            default => 255,
        };
        $asked = (int) ($this->raw['max'] ?? 0);

        return $asked > 0 ? min($asked, $limit) : $limit;
    }

    /** A product picker that holds a hand-picked list rather than one product. */
    public function multiple(): bool
    {
        return $this->type === 'product-picker' && ($this->raw['multiple'] ?? false) === true;
    }

    /** Most items a hand-picked list keeps (the storefront shows at most 48 products). */
    public function maxItems(): int
    {
        $asked = (int) ($this->raw['max'] ?? 0);

        return $asked > 0 ? min($asked, 48) : 48;
    }

    /** The finite list of values a range setting can take, in order. @return list<int|float> */
    public function rangeValues(): array
    {
        $out = [];
        $count = (int) round(($this->max() - $this->min()) / $this->step());
        for ($i = 0; $i <= $count && $i <= 1000; $i++) {
            $value = round($this->min() + $i * $this->step(), 4);
            $out[] = floor($value) === $value ? (int) $value : $value;
        }

        return $out;
    }
}
