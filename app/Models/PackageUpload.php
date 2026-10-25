<?php

namespace App\Models;

use App\Support\PackageArchive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One theme or extension archive uploaded in the admin, and its result.
 *
 * The row is written as pending before anything on disk changes, then
 * marked installed, updated, refused (nothing was changed) or failed (the
 * install started and was undone, or stopped part way).
 */
class PackageUpload extends Model
{
    public const PENDING = 'pending';

    public const INSTALLED = 'installed';

    public const UPDATED = 'updated';

    public const REFUSED = 'refused';

    public const FAILED = 'failed';

    /**
     * The most each text column holds (characters), as the migration
     * declares them. Every value is cut to fit before it is saved, so a
     * strict database never refuses the record of an upload.
     */
    public const LENGTHS = [
        'kind' => 20,
        'slug' => 64,
        'package' => 80,
        'version' => 40,
        'previous_version' => 40,
        'result' => 20,
        'message' => 2000,
        'filename' => 255,
    ];

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

    public function setAttribute($key, $value)
    {
        if (isset(self::LENGTHS[$key]) && $value !== null) {
            $value = PackageArchive::plain((string) $value, self::LENGTHS[$key]);
        }

        return parent::setAttribute($key, $value);
    }

    /** Whether an upload put this package's folder on disk. */
    public static function placed(string $package): bool
    {
        return static::where('package', $package)
            ->whereIn('result', [self::INSTALLED, self::UPDATED])
            ->exists();
    }
}
