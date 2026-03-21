<?php

namespace App\Http\Controllers\Api;

use App\Models\ApiSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait LogsApiCalls
{
    protected function logAndRespond(Request $request, mixed $data, int $status = 200, ?string $sku = null): JsonResponse
    {
        $response = response()->json($data, $status);

        ApiSyncLog::create([
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'sku' => $sku,
            'status_code' => $status,
            'response_time_ms' => (int) ((microtime(true) - LARAVEL_START) * 1000),
            'request_body' => $request->method() !== 'GET' ? json_encode($request->all(), JSON_UNESCAPED_UNICODE) : null,
            'response_body' => substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 5000),
            'ip_address' => $request->ip(),
        ]);

        return $response;
    }
}
