<?php

namespace App\Support;

use App\Models\Store;
use App\Services\ThemeService;
use Illuminate\Support\Facades\Storage;

/**
 * The store's own name and marks, for both surfaces that wear them.
 *
 * The admin shows the MERCHANT, never the platform: whoever is signed in
 * runs a store, and Venta is only the application it runs on. There is one
 * logo and one favicon (group 'identity', paths on the public disk),
 * resolved here for the storefront and the admin alike, so the two can
 * never disagree about what the store is called or which mark it wears.
 * The admin holds that one logo on a light plate rather than asking for a
 * second, reversed copy of it.
 *
 * The one thing this adds over an inline settings lookup is an existence
 * check. A settings row outlives the file it names: a logo deleted from
 * storage, or a database restored onto a machine that never had the uploads,
 * leaves a path pointing at nothing. Rather than render a broken image,
 * every accessor treats a missing file as an absent one, so a surface falls
 * back exactly as if nothing had been uploaded.
 *
 * The store's contact details (email, phone, address) live on the store
 * record itself, not in settings: they describe the store, and the store
 * email is also the address every outgoing email is sent from.
 */
class StoreIdentity
{
    /** Resolved once per request: three settings and at most two stats. */
    protected static ?array $memo = null;

    /** The store record's contact columns, resolved once per request. */
    protected static ?array $contact = null;

    /** The store's name, falling back to the application's. */
    public static function name(): string
    {
        return static::resolve()['name'];
    }

    /** Absolute URL of the uploaded logo, or null when there isn't a usable one. */
    public static function logoUrl(): ?string
    {
        return static::resolve()['logo'];
    }

    /** Absolute URL of the uploaded favicon, or null. */
    public static function faviconUrl(): ?string
    {
        return static::resolve()['favicon'];
    }

    /** First character of the store's name, for the fallback mark. */
    public static function initial(): string
    {
        return mb_strtoupper(mb_substr(static::name(), 0, 1));
    }

    /** The store's email: shown to shoppers, and the address mail is sent from. */
    public static function email(): ?string
    {
        return static::contact()['email'];
    }

    /** The store's phone number, or null when none is set. */
    public static function phone(): ?string
    {
        return static::contact()['phone'];
    }

    /** The store's postal address as one plain block of text, or null. */
    public static function address(): ?string
    {
        return static::contact()['address'];
    }

    /** @return array{email: ?string, phone: ?string, address: ?string} */
    protected static function contact(): array
    {
        if (static::$contact !== null) {
            return static::$contact;
        }

        $empty = ['email' => null, 'phone' => null, 'address' => null];

        try {
            $row = Store::query()
                ->whereKey((int) config('app.store_id', 1))
                ->first(['email', 'phone', 'address']);
        } catch (\Throwable) {
            // The table may not exist yet (during install or migration).
            // Not memoized, so the next call sees the store once it does.
            return $empty;
        }

        if ($row === null) {
            return static::$contact = $empty;
        }

        $clean = static function (mixed $value): ?string {
            $value = is_string($value) ? trim($value) : '';

            return $value === '' ? null : $value;
        };

        return static::$contact = [
            'email' => $clean($row->email),
            'phone' => $clean($row->phone),
            'address' => $clean($row->address),
        ];
    }

    /** @return array{name: string, logo: ?string, favicon: ?string} */
    protected static function resolve(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        $theme = app(ThemeService::class);

        return static::$memo = [
            'name' => $theme->get('identity.site_name') ?: config('app.name', 'VentaCart'),
            'logo' => static::url($theme->get('identity.logo')),
            'favicon' => static::url($theme->get('identity.favicon')),
        ];
    }

    protected static function url(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        try {
            if (! Storage::disk('public')->exists($path)) {
                return null;
            }
        } catch (\Throwable) {
            // An unreachable disk must not be the thing that takes the admin
            // panel down; the name alone is a complete identity.
            return null;
        }

        return asset('storage/' . $path);
    }

    /** Test seam: forget the per-request memo. */
    public static function flush(): void
    {
        static::$memo = null;
        static::$contact = null;
    }
}
