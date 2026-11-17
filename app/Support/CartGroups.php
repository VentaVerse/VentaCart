<?php

namespace App\Support;

use App\Contracts\CartLineGrouper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Groups of cart lines a theme draws under their own header (one per
 * seller, say), from the one grouper an extension registered.
 *
 * Core checks the grouper's answer before any theme sees it: every line is
 * in exactly one group, keys are well formed and unique, text is plain and
 * capped, links are safe. A grouper that fails or answers nonsense gives
 * the plain cart. A group's shipping is information for the shopper; what
 * the order costs is still worked out by core's checkout.
 */
final class CartGroups
{
    public const MAX_GROUPS = 50;

    public const KEY = '/^[a-z0-9-]{1,40}$/';

    /** The key of the group that collects lines the grouper named in no group. */
    public const REST = 'other';

    private static ?string $grouper = null;

    /** @var array<string, true> problems already logged in this process */
    private static array $logged = [];

    /**
     * Register the store's grouper, from an extension's service provider.
     * One grouper per store: a second, different one is refused and logged.
     *
     * @param  class-string<CartLineGrouper>  $grouperClass
     */
    public static function use(string $grouperClass): void
    {
        $grouperClass = ltrim($grouperClass, '\\');
        if (self::$grouper !== null && self::$grouper !== $grouperClass) {
            self::warn('second', 'A second cart grouper was refused; the store keeps the first.', ['kept' => self::$grouper, 'refused' => $grouperClass]);

            return;
        }

        self::$grouper = $grouperClass;
    }

    /** The registered grouper's class, or null. */
    public static function grouper(): ?string
    {
        return self::$grouper;
    }

    /** Take the registration away (tests, and a provider that switches itself off). */
    public static function forget(): void
    {
        self::$grouper = null;
        self::$logged = [];
    }

    /**
     * The checked groups for these lines, or null for the plain cart: no
     * grouper, a grouper that failed, or no lines.
     *
     * @param  Collection<int, \App\Models\CartItem>  $items
     * @return list<array{key: string, label: string, url: ?string, lines: list<int>, shipping: ?array{label: string, amount: ?float, note: ?string}}>|null
     */
    public static function for(Collection $items): ?array
    {
        if (self::$grouper === null || $items->isEmpty()) {
            return null;
        }

        $class = self::$grouper;
        if (! class_exists($class) || ! is_subclass_of($class, CartLineGrouper::class)) {
            self::warn('class', 'The registered cart grouper is not a CartLineGrouper; the cart is drawn plain.', ['class' => $class]);

            return null;
        }

        try {
            $answer = app($class)->group($items);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if (! is_array($answer)) {
            self::warn('answer', 'The cart grouper did not answer a list; the cart is drawn plain.', ['class' => $class]);

            return null;
        }

        return self::clean($answer, $items->map(fn ($item) => (int) $item->id)->values()->all());
    }

    /**
     * @param  array<mixed>  $answer  what the grouper returned
     * @param  list<int>  $lineIds  the cart's lines, in cart order
     * @return list<array{key: string, label: string, url: ?string, lines: list<int>, shipping: ?array{label: string, amount: ?float, note: ?string}}>
     */
    public static function clean(array $answer, array $lineIds): array
    {
        $position = array_flip($lineIds);
        $placed = [];
        $keys = [];
        $groups = [];

        foreach (array_values($answer) as $group) {
            if (count($groups) >= self::MAX_GROUPS - 1) {
                break;
            }
            if (! is_array($group)) {
                continue;
            }
            $key = $group['key'] ?? null;
            if (! is_string($key) || preg_match(self::KEY, $key) !== 1 || isset($keys[$key]) || $key === self::REST) {
                continue;
            }

            $lines = [];
            foreach ((array) ($group['lines'] ?? []) as $id) {
                $id = filter_var($id, FILTER_VALIDATE_INT);
                // First group wins; a line not in this cart is ignored.
                if ($id === false || ! isset($position[$id]) || isset($placed[$id])) {
                    continue;
                }
                $placed[$id] = true;
                $lines[] = $id;
            }
            if ($lines === []) {
                continue;
            }
            usort($lines, fn (int $a, int $b) => $position[$a] <=> $position[$b]);

            $url = SafeInput::link($group['url'] ?? null);
            $keys[$key] = true;
            $groups[] = [
                'key' => $key,
                'label' => trim(SafeInput::text($group['label'] ?? '', 80)),
                'url' => $url !== '' ? $url : null,
                'lines' => $lines,
                'shipping' => self::shipping($group['shipping'] ?? null),
            ];
        }

        $rest = array_values(array_filter($lineIds, fn (int $id) => ! isset($placed[$id])));
        if ($rest !== []) {
            $groups[] = ['key' => self::REST, 'label' => '', 'url' => null, 'lines' => $rest, 'shipping' => null];
        }

        return $groups;
    }

    /** @return array{label: string, amount: ?float, note: ?string}|null */
    private static function shipping(mixed $shipping): ?array
    {
        if (! is_array($shipping)) {
            return null;
        }

        $label = trim(SafeInput::text($shipping['label'] ?? '', 80));
        $amount = $shipping['amount'] ?? null;
        $amount = (is_int($amount) || is_float($amount)) && is_finite((float) $amount) && $amount >= 0 ? round((float) $amount, 2) : null;
        $note = trim(SafeInput::text($shipping['note'] ?? '', 160));

        if ($label === '' && $amount === null && $note === '') {
            return null;
        }

        return ['label' => $label, 'amount' => $amount, 'note' => $note !== '' ? $note : null];
    }

    private static function warn(string $reason, string $message, array $context): void
    {
        if (isset(self::$logged[$reason])) {
            return;
        }
        self::$logged[$reason] = true;
        Log::warning($message, $context);
    }
}
