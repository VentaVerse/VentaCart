<?php

namespace Extensions\StoreCredit;

use App\Support\SafeInput;
use Extensions\StoreCredit\Models\CustomerTransaction;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;

/**
 * A customer's store credit history as the account page lists it: one line
 * per ledger entry, newest first, with the balance after it.
 *
 * Other extensions may name entries and fold runs of them into one line
 * (a marketplace might show "Refund for order 1042" and fold the credit
 * an order spent across sellers into one line):
 *
 * - describe($fn): $fn(CustomerTransaction) returns the line's text, or
 *   null for the entry's own description. The first answer wins.
 * - group($fn): $fn(CustomerTransaction) returns a group key, or null.
 *   Consecutive entries with the same key become one line: the amounts
 *   added up, the text of the first (oldest) entry, the date and the
 *   balance after the last (newest) one. The first answer wins.
 *
 * Register from a listener of EVENT, which is fired the first time a
 * history is drawn, so the order extensions boot in does not matter:
 *
 *     Event::listen('store-credit.history', function (): void {
 *         StoreCreditHistory::describe(fn (CustomerTransaction $entry): ?string => …);
 *     });
 *
 * Lines are built from the newest entry on every page, so a group always
 * sits whole on one page and the pages never shift. Reading page n reads
 * the entries of the pages before it too; a customer's ledger is small.
 */
final class StoreCreditHistory
{
    public const EVENT = 'store-credit.history';

    public const PER_PAGE = 20;

    /** The furthest page read, which bounds the work one request does. */
    private const MAX_PAGE = 500;

    /** @var list<callable> */
    private static array $describers = [];

    /** @var list<callable> */
    private static array $groupers = [];

    private static bool $asked = false;

    public static function describe(callable $fn): void
    {
        self::$describers[] = $fn;
    }

    public static function group(callable $fn): void
    {
        self::$groupers[] = $fn;
    }

    /** Take every registration away (tests). */
    public static function forget(): void
    {
        self::$describers = [];
        self::$groupers = [];
        self::$asked = false;
    }

    /**
     * One page of the customer's lines, newest first.
     *
     * @param  int  $balance  the customer's balance now, in cents
     * @return Paginator<array{date: \Carbon\CarbonInterface, description: string, amount: float, balance: float, entries: int}>
     */
    public static function page(int $customerId, int $page, int $balance, int $perPage = self::PER_PAGE): Paginator
    {
        self::ask();
        $page = max(1, min(self::MAX_PAGE, $page));
        $skip = ($page - 1) * $perPage;
        // One line past the page tells whether an older page exists.
        $want = $skip + $perPage + 1;

        $lines = [];
        $open = null;
        $entries = CustomerTransaction::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lazy(200);

        foreach ($entries as $entry) {
            $amount = (int) round((float) $entry->amount * 100);
            $after = $balance;
            $balance -= $amount;
            $key = self::keyOf($entry);

            if ($open !== null && $key !== null && $open['key'] === $key) {
                $open['amount'] += $amount;
                $open['first'] = $entry;
                $open['entries']++;

                continue;
            }
            if ($open !== null) {
                $lines[] = $open;
                if (count($lines) >= $want) {
                    $open = null;
                    break;
                }
            }
            $open = ['key' => $key, 'last' => $entry, 'first' => $entry, 'amount' => $amount, 'balance' => $after, 'entries' => 1];
        }
        if ($open !== null) {
            $lines[] = $open;
        }

        // Only the lines shown are described.
        $shown = array_map(fn (array $line) => [
            'date' => $line['last']->created_at,
            'description' => self::label($line['first']),
            'amount' => $line['amount'] / 100,
            'balance' => $line['balance'] / 100,
            'entries' => $line['entries'],
        ], array_slice($lines, $skip, $perPage + 1));

        return new Paginator($shown, $perPage, $page, ['path' => Paginator::resolveCurrentPath()]);
    }

    /** Let other extensions register, once. */
    private static function ask(): void
    {
        if (self::$asked) {
            return;
        }
        self::$asked = true;
        try {
            Event::dispatch(self::EVENT);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function keyOf(CustomerTransaction $entry): ?string
    {
        foreach (self::$groupers as $fn) {
            try {
                $key = $fn($entry);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            if (is_string($key) && $key !== '') {
                return substr($key, 0, 191);
            }
        }

        return null;
    }

    /** The line's text: plain, as an extension named it or as the entry says. */
    private static function label(CustomerTransaction $entry): string
    {
        foreach (self::$describers as $fn) {
            try {
                $label = $fn($entry);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            if (is_string($label) && trim($label) !== '') {
                return trim(SafeInput::text($label, 200));
            }
        }

        return (string) $entry->description;
    }
}
