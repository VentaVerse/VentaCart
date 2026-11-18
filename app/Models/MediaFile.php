<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The width and height of one media library image, keyed by its path on the
 * public disk. Written on upload and filled in when an older image is first
 * browsed (see App\Support\MediaLibrary::dimensions()).
 */
class MediaFile extends Model
{
    protected $fillable = ['path', 'width', 'height'];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];
}
