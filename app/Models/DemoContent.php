<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One record installed by a template as demo content. See the migration for
 * why ownership lives in a register rather than on the records themselves.
 */
class DemoContent extends Model
{
    protected $table = 'demo_content';

    protected $fillable = ['preset', 'record_type', 'record_id'];

    public const PRODUCT = 'product';

    public const POST = 'post';

    /** @return list<int> */
    public static function idsFor(string $type): array
    {
        return static::where('record_type', $type)->pluck('record_id')->all();
    }
}
