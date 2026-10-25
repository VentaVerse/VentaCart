<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PackageRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageUploadRequest;
use App\Models\PackageUpload;
use App\Services\PackageUploader;
use App\Support\PackageArchive;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Extensions > Upload: install a theme or an extension from a .zip, or
 * update one an earlier upload installed. Super admin only.
 */
class PackageUploadController extends Controller
{
    public function show(): View
    {
        $limits = PackageUploader::limits();

        return view('admin.extensions.upload', [
            'uploadLimit' => PackageArchive::megabytes(PackageUploader::effectiveUploadLimit()),
            'serverCapsUpload' => PackageUploader::effectiveUploadLimit() < $limits['max_upload_bytes'],
            'recent' => PackageUpload::with('user:id,name')->latest('id')->limit(10)->get(),
            'result' => session('package_upload'),
        ]);
    }

    public function store(PackageUploadRequest $request, PackageUploader $uploader): RedirectResponse
    {
        try {
            $upload = $uploader->upload($request->file('package'), $request->user());
        } catch (PackageRefused $e) {
            return redirect()->route('admin.extensions.upload')
                ->with('package_upload', ['ok' => false, 'message' => $e->getMessage()]);
        }

        return redirect()->route('admin.extensions.upload')->with('package_upload', [
            'ok' => true,
            'message' => $upload->message,
            'kind' => $upload->kind,
            'slug' => $upload->slug,
            'version' => $upload->version,
            'previous_version' => $upload->previous_version,
            'updated' => $upload->result === PackageUpload::UPDATED,
        ]);
    }
}
