<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One theme or extension archive uploaded in the admin, and its result:
 * installed, updated or refused.
 */
class PackageUpload extends Model
{
    public const INSTALLED = 'installed';

    public const UPDATED = 'updated';

    public const REFUSED = 'refused';

    protected $fillable = [
        'user_id', 'kind', 'slug', 'package', 'version', 'previous_version',
        'result', 'message', 'filename', 'size', 'sha256',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Whether an upload put this package's folder on disk. */
    public static function placed(string $package): bool
    {
        return static::where('package', $package)
            ->whereIn('result', [self::INSTALLED, self::UPDATED])
            ->exists();
    }
}
