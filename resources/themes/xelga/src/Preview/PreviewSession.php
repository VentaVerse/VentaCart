<?php

namespace Themes\Xelga\Preview;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The draft preview window of one admin: a nonce, the admin
 * it belongs to, and when it closes (two hours, extended by the editor's
 * heartbeat). Without an open window for a signed-in admin with the theme
 * permission, no request ever sees a draft.
 *
 * Opening the editor again while the window is still open keeps its nonce.
 * A new nonce on every open made a preview request that was still on its
 * way (after a refresh, Back and Forward, or a second editor tab) arrive
 * with a nonce that had just been replaced, and the frame got the
 * published page instead of the preview. Exit closes the window, and the
 * next open starts a fresh one.
 *
 * The window lives in the cache under the admin, not inside the session. The editor sends several requests at once (the
 * preview, the presence heartbeat), and a session is saved whole at the end
 * of each request: a request that read the session before the window was
 * written would save it back without the window, and the preview would come
 * back as the published page. A cache entry of its own is never overwritten
 * by another request's session.
 */
final class PreviewSession
{
    public const KEY = 'xelga_preview';

    public const MINUTES = 120;

    public const PERMISSION = 'themes.edit';

    public static function open(User $user): array
    {
        $current = self::window();
        $nonce = ($current !== null && $current['user'] === (int) $user->id && $current['until'] >= now()->getTimestamp())
            ? $current['nonce']
            : Str::random(40);
        $window = ['nonce' => $nonce, 'until' => now()->addMinutes(self::MINUTES)->getTimestamp(), 'user' => (int) $user->id];
        self::store($window);

        return ['nonce' => $window['nonce'], 'until' => $window['until']];
    }

    public static function extend(User $user): ?int
    {
        $window = self::window();
        if ($window === null || $window['user'] !== (int) $user->id || $window['until'] < now()->getTimestamp()) {
            return null;
        }
        $window['until'] = now()->addMinutes(self::MINUTES)->getTimestamp();
        self::store($window);

        return $window['until'];
    }

    public static function close(): void
    {
        if (($key = self::cacheKey()) !== null) {
            Cache::forget($key);
        }
    }

    public static function valid(?string $nonce = null): bool
    {
        $window = self::window();
        if ($window === null || $window['until'] < now()->getTimestamp()) {
            return false;
        }

        $user = auth('web')->user();
        if (! $user instanceof User || (int) $user->id !== $window['user'] || ! $user->hasPermission(self::PERMISSION)) {
            return false;
        }

        return $nonce === null || hash_equals($window['nonce'], $nonce);
    }

    public static function nonce(): ?string
    {
        return self::window()['nonce'] ?? null;
    }

    /**
     * The window open for the signed-in admin, or null.
     *
     * @return array{nonce: string, until: int, user: int}|null
     */
    public static function window(): ?array
    {
        $key = self::cacheKey();
        if ($key === null) {
            return null;
        }
        $window = Cache::get($key);
        if (! is_array($window) || ! is_string($window['nonce'] ?? null) || ! is_int($window['until'] ?? null) || ! is_int($window['user'] ?? null)) {
            return null;
        }

        return $window;
    }

    /** Puts a window in place for the signed-in admin. */
    public static function store(array $window): void
    {
        if (($key = self::cacheKey()) !== null) {
            Cache::put($key, $window, now()->setTimestamp((int) $window['until'])->addMinute());
        }
    }

    /** One window per admin: Exit in any of their tabs closes it. */
    private static function cacheKey(): ?string
    {
        $user = auth('web')->user();

        return $user instanceof User ? self::KEY . ':' . (int) $user->id : null;
    }
}
