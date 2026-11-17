<?php

namespace Themes\Xelga\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Themes\Xelga\Http\Requests\PresenceRequest;
use Themes\Xelga\Preview\PreviewSession;

/**
 * The editor's heartbeat, every 30 seconds: says who else is editing and
 * keeps this admin's preview window open. Advice, not a lock: a closed
 * browser simply stops beating and drops off after a minute.
 */
class PresenceController extends Controller
{
    use RespondsWithJson;

    public const KEY = 'xelga:presence';

    public const SECONDS = 60;

    public function heartbeat(PresenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $now = now()->getTimestamp();

        $editors = Cache::lock(self::KEY . ':lock', 5)->block(3, function () use ($user, $request, $now) {
            $list = array_filter((array) Cache::get(self::KEY, []), fn ($e) => is_array($e) && ($e['at'] ?? 0) >= $now - self::SECONDS);
            $list[(int) $user->id] = [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'handle' => $request->validated('handle'),
                'since' => (int) ($list[(int) $user->id]['since'] ?? $now),
                'at' => $now,
            ];
            Cache::put(self::KEY, $list, self::SECONDS * 2);

            return $list;
        });

        $others = array_values(array_map(
            fn (array $e) => ['id' => $e['id'], 'name' => $e['name'], 'handle' => $e['handle'], 'since' => date(DATE_ATOM, $e['since'])],
            array_filter($editors, fn (array $e) => $e['id'] !== (int) $user->id),
        ));

        return $this->ok([
            'others' => $others,
            'preview_until' => PreviewSession::extend($user),
        ], $others === [] ? 'Only you are editing.' : 'Someone else is also editing.');
    }
}
