<?php

namespace Themes\Xelga\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document's published content at one publish, kept so a merchant can
 * look back and restore it into the draft. The newest 30 per document are
 * kept.
 *
 * @property int $id
 * @property int $document_id
 * @property int $version
 * @property array $content
 * @property string $source
 * @property ?int $created_by
 * @property \Illuminate\Support\Carbon $created_at
 */
class Revision extends Model
{
    public const KEEP = 30;

    public const UPDATED_AT = null;

    protected $table = 'xelga_revisions';

    protected $fillable = ['document_id', 'version', 'content', 'source', 'created_by'];

    protected $casts = [
        'content' => 'array',
        'version' => 'integer',
        'created_by' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
