<?php

namespace App\Support\Media;

/**
 * One place that stores library image paths: a column, a JSON settings bag,
 * anything an extension or theme keeps.
 *
 * Paths are matched exactly (case-sensitive, no wildcards), because a store
 * can hold `media/products/Demo` and `media/products/demo` side by side and
 * folder names can contain `_` and `%`.
 */
interface MediaReferenceSource
{
    /** The kind of user, as the admin shows it ("products", "menus"). */
    public function label(): string;

    /**
     * Whether a row here means the image is in use. A snapshot (an order
     * line, a saved revision) keeps its copy current when a file moves but
     * must not stop a merchant deleting an image nothing shows any more.
     */
    public function countsAsUse(): bool;

    /**
     * Who points at each path.
     *
     * Keys are stable strings; two sources that return the same key for one
     * path are one user, so a product counted through its main image and its
     * gallery is still one product.
     *
     * @param  array<int, string>  $paths
     * @return array<string, array<int, string>> path => owner keys
     */
    public function owners(array $paths): array;

    /**
     * Point every stored copy of $from at $to.
     *
     * Both are file paths or both are folder paths. A folder matches itself
     * and every value that starts with the folder followed by "/". The caller
     * owns the transaction.
     *
     * @return int rows changed
     */
    public function repoint(string $from, string $to): int;
}
