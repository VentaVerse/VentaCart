<?php

namespace App\Http\Requests\Admin;

use App\Services\PackageUploader;
use Illuminate\Foundation\Http\FormRequest;

/** A theme or extension archive sent from Extensions > Upload. */
class PackageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is super admin only (config/admin-permissions.php);
        // checked again here because this request installs code.
        return (bool) $this->user()?->isSuperAdmin();
    }

    public function rules(): array
    {
        $kilobytes = intdiv(PackageUploader::limits()['max_upload_bytes'], 1024);

        return [
            'package' => ['required', 'file', 'extensions:zip', 'mimetypes:application/zip,application/x-zip-compressed', "max:{$kilobytes}"],
        ];
    }

    public function messages(): array
    {
        $limit = \App\Support\PackageArchive::megabytes(PackageUploader::limits()['max_upload_bytes']);

        return [
            'package.required' => 'Choose a .zip file to upload.',
            'package.file' => 'The file did not arrive. It may be larger than this server accepts.',
            'package.uploaded' => 'The file did not arrive. It may be larger than this server accepts.',
            'package.extensions' => 'Upload a .zip file.',
            'package.mimetypes' => 'Upload a .zip file.',
            'package.max' => "The file is larger than {$limit}, the most a package upload may be.",
        ];
    }
}
