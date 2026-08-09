<?php

namespace App\Http\Requests\Admin;

use App\Support\IdentityAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * The store's name and marks, as submitted by the Identity card.
 *
 * Size and presence are handled by rules; what the file IS is settled by
 * reading its bytes, because the browser's filename and Content-Type are
 * both attacker-controlled. The extension the upload is finally stored
 * under comes out of that same inspection, never off the request.
 */
class StoreIdentityRequest extends FormRequest
{
    /** Extensions decided by inspection, keyed by field: 'logo' => 'png'. */
    private array $extensions = [];

    public function authorize(): bool
    {
        // The route already sits behind the admin auth middleware group.
        return true;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:120'],
            'logo' => ['nullable', 'file', 'max:'.IdentityAsset::LOGO_MAX_KB],
            'favicon' => ['nullable', 'file', 'max:'.IdentityAsset::FAVICON_MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.max' => 'The logo may not be larger than '.IdentityAsset::LOGO_MAX_KB.' KB.',
            'favicon.max' => 'The favicon may not be larger than '.IdentityAsset::FAVICON_MAX_KB.' KB.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->inspect(
                    $validator,
                    'logo',
                    fn (string $path) => IdentityAsset::logoExtension($path),
                    'The logo must be a PNG, JPG or WebP image between '
                        .IdentityAsset::LOGO_MIN_PX.' and '.IdentityAsset::LOGO_MAX_PX.' pixels.'
                );

                $this->inspect(
                    $validator,
                    'favicon',
                    fn (string $path) => IdentityAsset::faviconExtension($path),
                    'The favicon must be a PNG, ICO or SVG icon of at most '
                        .IdentityAsset::FAVICON_MAX_PX.' pixels, with no script in it.'
                );
            },
        ];
    }

    private function inspect(Validator $validator, string $field, callable $identify, string $message): void
    {
        $file = $this->file($field);

        if (! $file instanceof UploadedFile) {
            return;
        }

        if (! $file->isValid()) {
            $validator->errors()->add($field, 'The '.$field.' failed to upload.');

            return;
        }

        $extension = $identify($file->getRealPath());

        if ($extension === null) {
            $validator->errors()->add($field, $message);

            return;
        }

        $this->extensions[$field] = $extension;
    }

    /** The extension a validated upload must be stored under. */
    public function extensionFor(string $field): ?string
    {
        return $this->extensions[$field] ?? null;
    }
}
