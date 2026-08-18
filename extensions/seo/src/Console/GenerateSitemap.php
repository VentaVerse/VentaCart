<?php

namespace Extensions\Seo\Console;

use Extensions\Blog\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Console\Command;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Generate XML sitemap for all public pages';

    public function handle(): int
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $urls = [];

        // Homepage
        $urls[] = [
            'loc' => $baseUrl . '/',
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        // Categories
        $categories = Category::where('is_active', true)->get(['slug', 'updated_at']);
        foreach ($categories as $category) {
            $urls[] = [
                'loc' => $baseUrl . '/category/' . $category->slug,
                'lastmod' => $category->updated_at?->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        // Products
        $products = Product::where('is_active', true)->get(['slug', 'updated_at']);
        foreach ($products as $product) {
            $urls[] = [
                'loc' => $baseUrl . '/product/' . $product->slug,
                'lastmod' => $product->updated_at?->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        // Brands index
        $urls[] = [
            'loc' => $baseUrl . '/brand',
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ];

        // Brands
        $brands = Brand::where('is_active', true)->whereNotNull('slug')->get(['slug', 'updated_at']);
        foreach ($brands as $brand) {
            $urls[] = [
                'loc' => $baseUrl . '/brand/' . $brand->slug,
                'lastmod' => $brand->updated_at?->toW3cString(),
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];
        }

        // Blog Posts (only while the blog extension exists)
        $posts = class_exists(BlogPost::class)
            ? BlogPost::where('is_published', true)->get(['slug', 'updated_at'])
            : collect();
        foreach ($posts as $post) {
            $urls[] = [
                'loc' => $baseUrl . '/blog/' . $post->slug,
                'lastmod' => $post->updated_at?->toW3cString(),
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ];
        }

        // Blog index
        if ($posts->isNotEmpty()) {
            $urls[] = [
                'loc' => $baseUrl . '/blog',
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ];
        }

        // CMS Pages (about-us, privacy-policy, payment-and-delivery, etc.)
        $pages = Page::where('is_active', true)->get(['slug', 'updated_at']);
        foreach ($pages as $page) {
            $urls[] = [
                'loc' => $baseUrl . '/' . $page->slug,
                'lastmod' => $page->updated_at?->toW3cString(),
                'changefreq' => 'monthly',
                'priority' => '0.4',
            ];
        }

        // Contact page (statically routed)
        $urls[] = [
            'loc' => $baseUrl . '/contact',
            'changefreq' => 'monthly',
            'priority' => '0.3',
        ];

        // Build XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
            if (!empty($url['lastmod'])) {
                $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            }
            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        $path = public_path('sitemap.xml');
        file_put_contents($path, $xml);

        $this->info('Sitemap generated with ' . count($urls) . ' URLs at ' . $path);

        return 0;
    }
}
