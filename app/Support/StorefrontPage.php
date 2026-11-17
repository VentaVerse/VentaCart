<?php

namespace App\Support;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;

/**
 * Draws a storefront page of a page type an extension registered
 * (StorefrontPageType::register) with the live theme's own page view when
 * the theme has one, so the merchant can lay the page out in the theme's
 * editor. Any other theme gets the extension's own view.
 *
 * The theme's view is the un-namespaced `extension-page`. A live theme's
 * views folder is first in the view finder, so the view exists only while
 * the theme that provides it is the one drawing the store.
 */
final class StorefrontPage
{
    /** The optional theme view (theme contract, section 4). */
    public const VIEW = 'extension-page';

    /**
     * @param  string  $pageType  a page type an extension registered, e.g. 'shop'
     * @param  string  $fallbackView  the extension's own view, e.g. 'marketplace::shop.show'
     * @param  array<string, mixed>  $data  `title` (string), `subject` (an optional model the page is about),
     *                                     `breadcrumbs` (optional list of {label, url?}), and anything the page's sections read
     */
    public static function render(string $pageType, string $fallbackView, array $data = []): View
    {
        if (! self::isExtensionType($pageType)) {
            Log::warning('A storefront page was drawn for a page type no extension registered; the extension view is used.', ['page_type' => $pageType]);

            return view($fallbackView, $data);
        }

        if (! view()->exists(self::VIEW)) {
            return view($fallbackView, $data);
        }

        $title = $data['title'] ?? null;

        return view(self::VIEW, [
            'pageType' => $pageType,
            'title' => is_string($title) ? $title : null,
            'subject' => is_object($data['subject'] ?? null) ? $data['subject'] : null,
            'breadcrumbs' => self::breadcrumbs($data['breadcrumbs'] ?? null),
            'page' => $data,
        ]);
    }

    /** A page type an extension added: registered, and not one of core's. */
    public static function isExtensionType(string $pageType): bool
    {
        return ! array_key_exists($pageType, StorefrontPageType::TYPES) && StorefrontPageType::exists($pageType);
    }

    /**
     * The trail as plain values: a label of text, and a link only when it
     * is one SafeInput keeps. Anything else is left out.
     *
     * @return list<array{label: string, url: ?string}>
     */
    public static function breadcrumbs(mixed $trail): array
    {
        if (! is_array($trail)) {
            return [];
        }

        $out = [];
        foreach (array_slice(array_values($trail), 0, 10) as $crumb) {
            if (! is_array($crumb)) {
                continue;
            }
            $label = trim(SafeInput::text($crumb['label'] ?? '', 120));
            if ($label === '') {
                continue;
            }
            $url = SafeInput::link($crumb['url'] ?? null);
            $out[] = ['label' => $label, 'url' => $url !== '' ? $url : null];
        }

        return $out;
    }
}
