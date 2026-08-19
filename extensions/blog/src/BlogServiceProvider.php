<?php

namespace Extensions\Blog;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'blog');

        // Core's Tag gains its blog relation only while Blog is installed. The
        // counterpart comment lives in App\Models\Tag.
        \App\Models\Tag::resolveRelationUsing('blogPosts', function (\App\Models\Tag $tag) {
            return $tag->belongsToMany(\Extensions\Blog\Models\BlogPost::class, 'blog_post_tag');
        });
    }
}
