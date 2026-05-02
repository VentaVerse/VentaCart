<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Tag;
use App\Services\ThemeService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request, ThemeService $theme)
    {
        $settings = $theme->getGroup('blog');
        $perPage = $settings['posts_per_page'] ?? 12;

        $query = BlogPost::where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with('category', 'tags');

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->input('category')));
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $request->input('tag')));
        }

        $posts = $query->latest('published_at')->paginate($perPage)->withQueryString();

        $categories = BlogCategory::withCount(['posts' => function ($q) {
            $q->where('is_published', true)->where('published_at', '<=', now());
        }])->orderBy('sort_order')->get();

        $tags = Tag::whereHas('blogPosts', function ($q) {
            $q->where('is_published', true)->where('published_at', '<=', now());
        })->orderBy('name')->get();

        $view = $request->input('view', $settings['default_view'] ?? 'grid');

        $page = (int) request()->query('page', 1);
        view()->share('canonical', route('blog.index') . ($page > 1 ? '?page=' . $page : ''));

        return view('blog.index', compact('posts', 'categories', 'tags', 'view', 'settings'));
    }

    public function show(BlogPost $post, ThemeService $theme)
    {
        if (!$post->is_published) {
            abort(404);
        }

        $post->increment('view_count');
        $post->load('category', 'tags');

        $settings = $theme->getGroup('blog');

        $relatedPosts = collect();
        if ($settings['show_related_articles'] ?? true) {
            $tagIds = $post->tags->pluck('id');
            $relatedPosts = BlogPost::where('is_published', true)
                ->where('id', '!=', $post->id)
                ->where('published_at', '<=', now())
                ->where(function ($q) use ($post, $tagIds) {
                    $q->where('blog_category_id', $post->blog_category_id);
                    if ($tagIds->isNotEmpty()) {
                        $q->orWhereHas('tags', fn ($q2) => $q2->whereIn('tags.id', $tagIds));
                    }
                })
                ->latest('published_at')
                ->limit(4)
                ->get();
        }

        $ogTitle = $post->og_title ?: null;
        $ogDescription = $post->og_description ?: null;

        view()->share('canonical', route('blog.show', $post->slug));

        return view('blog.show', compact('post', 'relatedPosts', 'settings', 'ogTitle', 'ogDescription'));
    }
}
