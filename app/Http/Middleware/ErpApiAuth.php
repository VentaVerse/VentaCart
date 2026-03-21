<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class ErpApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $storedToken = Setting::where('group', 'api')->where('key', 'erp_token')->value('value');

        if (!$token || !$storedToken || !hash_equals($storedToken, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
