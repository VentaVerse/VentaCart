<?php

namespace Themes\Xelga\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Every editor endpoint answers in one shape:
 * {"success": bool, "message": string, "code"?: string, "data": mixed, "errors"?: object}.
 * `code` names the kind of refusal (invalid, conflict, readability, ...), so
 * the editor can react without reading the message.
 */
trait RespondsWithJson
{
    protected function ok(mixed $data, string $message = 'Done.', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Cache-Control', 'no-store, private');
    }

    protected function fail(string $message, string $code, int $status, mixed $data = null, ?array $errors = null): JsonResponse
    {
        $body = ['success' => false, 'message' => $message, 'code' => $code, 'data' => $data];
        if ($errors !== null) {
            $body['errors'] = (object) $errors;
        }

        return response()->json($body, $status, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Cache-Control', 'no-store, private');
    }
}
