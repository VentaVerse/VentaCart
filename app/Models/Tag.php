<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_tag');
    }

    // A tag's blog posts are NOT declared here. Blog is an optional extension
    // that can be uninstalled, and a relation naming a class that is not
    // loaded fails whenever anything touches it, which showed up as a 500 on
    // admin pages that never mentioned blogging. BlogServiceProvider attaches
    // the relation to this model when it boots (Eloquent's
    // resolveRelationUsing), so core stays unaware of it and callers ask
    // $tag->isRelation('blogPosts') before using it.
}
