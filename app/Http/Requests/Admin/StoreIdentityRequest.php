<?php

namespace App\Http\Requests\Admin;

use App\Rules\MediaImage;
use App\Support\IdentityAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;

/**
 * The store's name, marks and contact details, as submitted by the General
 * page.
 *
 * Size and presence are handled by rules; what the file IS is settled by
 * reading its bytes, because the browser's filename and Content-Type are
 * both attacker-controlled. The extension the upload is finally stored
 * under comes out of that same inspection, never off the request.
 */
class StoreIdentityRequest extends FormRequest
{
    /** The store record's contact columns this form edits. */
    public const CONTACT_FIELDS = ['email', 'phone', 'address'];

    /** Extensions decided by inspection, keyed by field: 'logo' => 'png'. */
    private array $extensions = [];

    /** The file each accepted mark is copied from, keyed by field. */
    private array $sources = [];

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
            // A mark chosen from the media library. It is copied into the
            // store identity folder on save, so the library image can be
            // deleted afterwards without breaking the store.
            'logo_media' => ['nullable', 'string', new MediaImage()],
            'favicon_media' => ['nullable', 'string', new MediaImage()],
            // The store email becomes every outgoing email's From address,
            // so it must also pass the check the mailer applies at boot.
            'email' => ['nullable', 'string', 'email:rfc,filter_unicode', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** Trim the contact fields, so whitespace alone never counts as a value. */
    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (self::CONTACT_FIELDS as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $value = trim($this->input($field));
                $trimmed[$field] = $value === '' ? null : $value;
            }
        }

        if ($trimmed !== []) {
            $this->merge($trimmed);
        }
    }

    /**
     * The contact values submitted with this save, keyed by store column.
     * Only fields present in the request are returned, so a save that does
     * not carry them leaves the stored values alone.
     *
     * @return array<string, ?string>
     */
    public function contact(): array
    {
        $values = [];

        foreach (self::CONTACT_FIELDS as $field) {
            if ($this->exists($field)) {
                $value = $this->validated($field);
                $values[$field] = is_string($value) && $value !== '' ? $value : null;
            }
        }

        return $values;
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

        // An uploaded file wins over a library choice sent with it.
        if (! $file instanceof UploadedFile) {
            $this->inspectLibraryChoice($validator, $field, $identify, $message);

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
        $this->sources[$field] = $file->getRealPath();
    }

    /** A library image goes through the same checks as an upload, on its disk path. */
    private function inspectLibraryChoice(Validator $validator, string $field, callable $identify, string $message): void
    {
        $chosen = $this->input($field.'_media');

        if (! is_string($chosen) || $chosen === '') {
            return;
        }

        $path = Storage::disk('public')->path($chosen);
        $extension = is_file($path) ? $identify($path) : null;

        if ($extension === null) {
            $validator->errors()->add($field.'_media', $message);

            return;
        }

        $this->extensions[$field] = $extension;
        $this->sources[$field] = $path;
    }

    /** The file a validated mark is copied from, or null when none was sent. */
    public function sourceFor(string $field): ?string
    {
        return $this->sources[$field] ?? null;
    }

    /** The extension a validated upload must be stored under. */
    public function extensionFor(string $field): ?string
    {
        return $this->extensions[$field] ?? null;
    }
}
