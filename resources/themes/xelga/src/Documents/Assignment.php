<?php

namespace Themes\Xelga\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A custom template chosen for one product, category, brand, page or post.
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property int $document_id
 */
class Assignment extends Model
{
    public const SUBJECTS = ['product', 'category', 'brand', 'page', 'blog_post'];

    protected $table = 'xelga_assignments';

    protected $fillable = ['subject_type', 'subject_id', 'document_id'];

    protected $casts = [
        'subject_id' => 'integer',
        'document_id' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
