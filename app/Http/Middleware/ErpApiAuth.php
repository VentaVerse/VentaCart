<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ErpApiAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $encryptedToken = Setting::where('group', 'api')->where('key', 'erp_token')->value('value');

        if (!$token || !$encryptedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $storedToken = Crypt::decryptString($encryptedToken);
        } catch (\Throwable $e) {
            // Fallback: token may not be encrypted yet (pre-migration)
            $storedToken = $encryptedToken;
        }

        if (!hash_equals($storedToken, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
