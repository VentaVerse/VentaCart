<?php

namespace Extensions\CourierFulfillment\Couriers\Lbc;

/**
 * LBC's packaging products by PKG code, from Table 2.0 "PKG or PRODUCT ID
 * LIST" of the Pickup Request API. The Air Waybill names the product line
 * (CARGO or DOCUMENTS) and the product by name, so both are kept here.
 */
final class LbcPackaging
{
    /** @var array<int, array{0:string,1:string}> PKG => [product line, product name] */
    public const NAMES = [
        2 => ['CARGO', 'PLASTIC'],
        4 => ['CARGO', 'CRATE'],
        6 => ['CARGO', 'KILOBOX-S'],
        7 => ['CARGO', 'KILOBOX-L'],
        16 => ['CARGO', 'BOX'],
        63 => ['CARGO', 'KILOBOX-M'],
        64 => ['CARGO', 'KILOBOX-XL'],
        79 => ['CARGO', 'PIECE'],
        80 => ['CARGO', 'SACK'],
        164 => ['CARGO', 'KILOBOX SLIM'],
        165 => ['CARGO', 'KILOBOX MINI'],
        3 => ['DOCUMENTS', 'N-POUCH REGULAR (NPR)'],
        8 => ['DOCUMENTS', 'N-PACK LARGE'],
        9 => ['DOCUMENTS', 'X POUCH'],
        45 => ['DOCUMENTS', 'N-PACK SMALL'],
        61 => ['DOCUMENTS', 'N-POUCH XL (NPXL)'],
        62 => ['DOCUMENTS', 'N-POUCH SS (NPSS)'],
    ];

    public static function productLine(int $pkg): string
    {
        return self::NAMES[$pkg][0] ?? 'CARGO';
    }

    public static function productName(int $pkg): string
    {
        return self::NAMES[$pkg][1] ?? (string) $pkg;
    }
}
