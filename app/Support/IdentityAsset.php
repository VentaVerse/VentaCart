<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * What an uploaded identity asset actually is, read from its own bytes.
 *
 * The client's filename and Content-Type are never consulted: both are
 * attacker-controlled, and the extension we store the file under is the one
 * decided here. An upload that cannot be identified is refused rather than
 * repaired, so nothing unrecognised ever reaches the public disk.
 */
final class IdentityAsset
{
    /** Upload ceilings, in kilobytes, shared by the request rules and the copy. */
    public const LOGO_MAX_KB = 512;
    public const FAVICON_MAX_KB = 256;

    /** Pixel sanity. A logo below this is an accident; above it is a photograph. */
    public const LOGO_MIN_PX = 24;
    public const LOGO_MAX_PX = 2000;
    public const FAVICON_MIN_PX = 16;
    public const FAVICON_MAX_PX = 512;

    /**
     * Everything an SVG favicon is allowed to contain. An allowlist, because
     * a blocklist of dangerous elements is only ever as current as the last
     * browser feature; anything unrecognised is treated as dangerous.
     */
    private const SVG_ELEMENTS = [
        'svg', 'g', 'title', 'desc', 'defs', 'symbol', 'path', 'rect', 'circle',
        'ellipse', 'line', 'polyline', 'polygon', 'lineargradient',
        'radialgradient', 'stop', 'clippath', 'mask', 'text', 'tspan',
    ];

    /** The file extension an acceptable logo should be stored under, or null. */
    public static function logoExtension(string $path): ?string
    {
        return self::raster(
            $path,
            [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'],
            self::LOGO_MIN_PX,
            self::LOGO_MAX_PX
        );
    }

    /** The file extension an acceptable favicon should be stored under, or null. */
    public static function faviconExtension(string $path): ?string
    {
        $png = self::raster(
            $path,
            [IMAGETYPE_PNG => 'png'],
            self::FAVICON_MIN_PX,
            self::FAVICON_MAX_PX
        );

        if ($png !== null) {
            return $png;
        }

        if (self::isIcon($path)) {
            return 'ico';
        }

        return self::isSafeSvg($path) ? 'svg' : null;
    }

    /**
     * Identify a raster image by its header and hold it to a pixel range.
     *
     * @param  array<int, string>  $accepted  image type constant => extension
     */
    private static function raster(string $path, array $accepted, int $min, int $max): ?string
    {
        $info = @getimagesize($path);

        if ($info === false || ! isset($info[2], $accepted[$info[2]])) {
            return null;
        }

        [$width, $height] = $info;

        if ($width < $min || $height < $min || $width > $max || $height > $max) {
            return null;
        }

        return $accepted[$info[2]];
    }

    /**
     * A Windows icon: a 6 byte directory header (reserved, type 1, then a
     * plausible image count) followed by at least one 16 byte entry.
     */
    private static function isIcon(string $path): bool
    {
        $header = @file_get_contents($path, false, null, 0, 6);

        if ($header === false || strlen($header) < 6) {
            return false;
        }

        $parts = unpack('vreserved/vtype/vcount', $header);

        if ($parts === false || $parts['reserved'] !== 0 || $parts['type'] !== 1) {
            return false;
        }

        if ($parts['count'] < 1 || $parts['count'] > 32) {
            return false;
        }

        return (int) @filesize($path) >= 6 + ($parts['count'] * 16);
    }

    /**
     * An SVG we are willing to serve from our own origin.
     *
     * Same-origin SVG is script-capable, so the document must parse as XML
     * and contain nothing outside the allowlist: no doctype (entity
     * expansion), no unknown element, no event handler, no link of any kind,
     * and no value reaching for another scheme.
     */
    private static function isSafeSvg(string $path): bool
    {
        $source = @file_get_contents($path, false, null, 0, (self::FAVICON_MAX_KB * 1024) + 1);

        if ($source === false || trim($source) === '') {
            return false;
        }

        if (stripos($source, '<!DOCTYPE') !== false || stripos($source, '<!ENTITY') !== false) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $parsed = $document->loadXML($source, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $parsed || ! $document->documentElement instanceof DOMElement) {
            return false;
        }

        if (strtolower($document->documentElement->localName) !== 'svg') {
            return false;
        }

        return self::isSafeSvgNode($document->documentElement);
    }

    private static function isSafeSvgNode(DOMNode $node): bool
    {
        if ($node instanceof DOMElement) {
            if (! in_array(strtolower($node->localName), self::SVG_ELEMENTS, true)) {
                return false;
            }

            foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
                if (! self::isSafeSvgAttribute($attribute->nodeName, (string) $attribute->nodeValue)) {
                    return false;
                }
            }
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && ! self::isSafeSvgNode($child)) {
                return false;
            }
        }

        return true;
    }

    private static function isSafeSvgAttribute(string $name, string $value): bool
    {
        $name = strtolower($name);

        // Event handlers, and any link at all: a favicon needs neither.
        if (str_starts_with($name, 'on') || $name === 'href' || str_ends_with($name, ':href')) {
            return false;
        }

        $collapsed = strtolower(preg_replace('/\s+/', '', $value) ?? '');

        return ! str_contains($collapsed, 'javascript:')
            && ! str_contains($collapsed, 'data:text/html')
            && ! str_contains($collapsed, '<script');
    }
}
