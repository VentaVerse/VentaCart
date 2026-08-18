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

    public function blogPosts(): BelongsToMany
    {
        return $this->belongsToMany(\Extensions\Blog\Models\BlogPost::class, 'blog_post_tag');
    }
}
