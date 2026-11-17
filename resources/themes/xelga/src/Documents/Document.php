<?php

namespace Themes\Xelga\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One saved unit of editor state: the theme settings, a section group
 * (header, footer) or a page template. The published content is what
 * shoppers see; the draft, when there is one, only the editor preview.
 *
 * @property int $id
 * @property string $handle
 * @property string $kind
 * @property ?string $page_type
 * @property ?string $label
 * @property ?array $published
 * @property int $published_version
 * @property ?\Illuminate\Support\Carbon $published_at
 * @property ?int $published_by
 * @property ?array $draft
 * @property int $draft_version
 * @property ?int $draft_base_version
 * @property ?int $draft_updated_by
 * @property ?\Illuminate\Support\Carbon $draft_updated_at
 */
class Document extends Model
{
    protected $table = 'xelga_documents';

    protected $fillable = ['handle', 'kind', 'page_type', 'label'];

    protected $casts = [
        'published' => 'array',
        'draft' => 'array',
        'published_version' => 'integer',
        'draft_version' => 'integer',
        'draft_base_version' => 'integer',
        'published_by' => 'integer',
        'draft_updated_by' => 'integer',
        'published_at' => 'datetime',
        'draft_updated_at' => 'datetime',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class, 'document_id');
    }
}
