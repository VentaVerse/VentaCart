<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\ApiAbility;
use Closure;
use Illuminate\Http\Request;

/**
 * Who is calling, and may they do this.
 *
 * Two separate refusals, deliberately distinct: 401 means the key is not
 * recognised, 403 means it is recognised and not permitted. Collapsing them
 * would leave an integrator regenerating a perfectly good key to fix a
 * permission they simply were not given.
 */
class ErpApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        $secret = $request->bearerToken();

        if (! $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $key = ApiKey::findBySecret($secret);

        if (! $key) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $needs = ApiAbility::for($request);

        // A route nobody claimed is refused rather than allowed. An endpoint
        // added without an entry in the map would otherwise be open to every
        // key regardless of its ticks, and nothing about that looks wrong from
        // the outside. ApiAbilityCoverageTest is what stops this happening in
        // practice; this is what stops it mattering if it ever does.
        if ($needs === null) {
            return response()->json([
                'error' => 'This endpoint has no permission assigned and cannot be called.',
            ], 403);
        }

        [$area, $mode] = $needs;

        if (! $key->allows($area, $mode)) {
            return response()->json([
                'error' => "This key is not permitted to {$mode} {$area}.",
            ], 403);
        }

        // Recorded so a key nobody uses is visibly safe to delete. Written
        // without touching updated_at, which would otherwise make every key
        // look freshly edited.
        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}
