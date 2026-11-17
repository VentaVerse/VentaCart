<?php

namespace Themes\Xelga\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Themes\Xelga\Preview\PreviewSession;

/**
 * A request from the theme editor. The route map already demands the theme
 * permission; this checks it again, and answers refusals in the editor's
 * JSON shape.
 */
abstract class EditorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof \App\Models\User && $user->hasPermission(PreviewSession::PERMISSION);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first() ?: 'The request is not valid.',
            'code' => 'invalid',
            'data' => null,
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'You do not have permission to do this. Ask a super admin for access.',
            'code' => 'forbidden',
            'data' => null,
        ], 403));
    }
}
