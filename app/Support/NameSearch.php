<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Finding a person by the name you would say out loud.
 *
 * A name typed into a search box is a set of words, not a string. Matching it
 * as one unbroken run of characters means the search cannot step over
 * anything sitting between those words, so a customer stored as
 * first_name "Paolo Andres", last_name "Reyes" is not found by "Paolo Reyes".
 * On the live NGD table that was 1,227 customers out of 3,547.
 *
 * It is not an import artefact. The sign-up form has two boxes, first and
 * last, so people type a middle name or a compound given name into the first
 * one, and five of the twenty-nine customers created natively in Venta
 * already had the shape. Any new store will grow its own.
 *
 * So: split on whitespace, and require EVERY word to appear in the name
 * somewhere. "Paolo Reyes" becomes "has Paolo somewhere AND has Reyes
 * somewhere", which finds the record whatever sits between the two, in
 * either order, and whether or not the name was ever split into two columns.
 * One rule covers the legacy shape and the tidy shape.
 */
class NameSearch
{
    /**
     * Words beyond this are ignored.
     *
     * A person types two or three. A pasted paragraph would otherwise build a
     * query with a condition per word, and each one is an AND, so the result
     * is empty anyway: the cap costs nothing real and bounds the query.
     */
    public const MAX_WORDS = 10;

    /**
     * Narrow $query to rows whose name carries every word in $search.
     *
     * $wholeFields are matched against the WHOLE search string instead, never
     * split: an email address or a phone number is one token with its own
     * punctuation, and splitting "a@b.test" on spaces would be meaningless.
     * They wide the result (OR), because someone pasting an address expects
     * to find it.
     *
     * @param  list<string>  $wholeFields  columns matched whole, e.g. email, phone
     * @param  list<string>  $nameFields   columns the words are matched against
     */
    public static function apply(
        Builder $query,
        ?string $search,
        array $wholeFields = [],
        array $nameFields = ['first_name', 'last_name'],
    ): Builder {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $words = array_slice(preg_split('/\s+/', $search) ?: [], 0, self::MAX_WORDS);

        return $query->where(function (Builder $outer) use ($words, $search, $wholeFields, $nameFields) {
            // Every word must appear somewhere in the name.
            $outer->where(function (Builder $all) use ($words, $nameFields) {
                foreach ($words as $word) {
                    $all->where(function (Builder $any) use ($word, $nameFields) {
                        foreach ($nameFields as $field) {
                            $any->orWhere($field, 'like', '%' . self::escape($word) . '%');
                        }
                    });
                }
            });

            // Or the whole string is one of these, matched intact.
            foreach ($wholeFields as $field) {
                $outer->orWhere($field, 'like', '%' . self::escape($search) . '%');
            }
        });
    }

    /**
     * LIKE wildcards typed by a person are literal characters.
     *
     * Without this, searching for "%" matches every row, and a customer whose
     * name genuinely contains an underscore cannot be found by typing it.
     */
    private static function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
