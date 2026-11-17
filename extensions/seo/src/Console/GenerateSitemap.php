<?php

namespace Extensions\Seo\Console;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\SitemapUrls;
use Extensions\Blog\Models\BlogPost;
use Illuminate\Console\Command;

/**
 * The store's XML sitemap: core's pages, then the pages extensions add
 * (App\Support\SitemapUrls).
 *
 * Entries are written as they are read, so a large catalog is never held
 * in memory. A sitemap file holds at most 50,000 addresses; past that the
 * entries go into numbered files (sitemap-1.xml, sitemap-2.xml, ...) and
 * sitemap.xml becomes the index that lists them, as the sitemap format
 * requires.
 */
class GenerateSitemap extends Command
{
    /** The most addresses one sitemap file may hold. */
    public const PER_FILE = 50000;

    protected $signature = 'sitemap:generate {--path= : Where to write the sitemap (default: public/sitemap.xml)}';

    protected $description = 'Generate XML sitemap for all public pages';

    private string $baseUrl;

    private string $path;

    /** @var list<string> finished numbered files, in order */
    private array $files = [];

    /** @var resource|null the numbered file being written */
    private $handle = null;

    private ?string $handlePath = null;

    private int $inFile = 0;

    private int $total = 0;

    /** @var array<string, true> addresses written, as keys, so extensions never repeat one */
    private array $listed = [];

    public function handle(): int
    {
        $this->baseUrl = rtrim(config('app.url'), '/');
        $this->path = (string) ($this->option('path') ?: public_path('sitemap.xml'));
        if (! str_ends_with($this->path, '.xml') || ! is_dir(dirname($this->path)) || ! is_writable(dirname($this->path))) {
            $this->error('The sitemap path must be an .xml file in a folder that can be written to.');

            return self::FAILURE;
        }

        try {
            $this->writeCorePages();
            foreach (SitemapUrls::all($this->listed) as $entry) {
                $this->write($entry);
            }
            $this->finish();
        } catch (\Throwable $e) {
            $this->abandon();
            throw $e;
        }

        $this->info('Sitemap generated with ' . $this->total . ' URLs at ' . $this->path);

        return self::SUCCESS;
    }

    private function writeCorePages(): void
    {
        $baseUrl = $this->baseUrl;

        // Homepage
        $this->write(['loc' => $baseUrl . '/', 'changefreq' => 'daily', 'priority' => '1.0']);

        // Categories
        foreach (Category::where('is_active', true)->select(['id', 'slug', 'updated_at'])->lazyById(1000) as $category) {
            $this->write([
                'loc' => $baseUrl . '/category/' . $category->slug,
                'lastmod' => $category->updated_at?->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ]);
        }

        // Products
        foreach (Product::where('is_active', true)->select(['id', 'slug', 'updated_at'])->lazyById(1000) as $product) {
            $this->write([
                'loc' => $baseUrl . '/product/' . $product->slug,
                'lastmod' => $product->updated_at?->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ]);
        }

        // Brands index
        $this->write(['loc' => $baseUrl . '/brand', 'changefreq' => 'weekly', 'priority' => '0.7']);

        // Brands
        foreach (Brand::where('is_active', true)->whereNotNull('slug')->select(['id', 'slug', 'updated_at'])->lazyById(1000) as $brand) {
            $this->write([
                'loc' => $baseUrl . '/brand/' . $brand->slug,
                'lastmod' => $brand->updated_at?->toW3cString(),
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ]);
        }

        // Blog Posts (only while the blog extension exists)
        $posts = class_exists(BlogPost::class)
            ? BlogPost::where('is_published', true)->get(['slug', 'updated_at'])
            : collect();
        foreach ($posts as $post) {
            $this->write([
                'loc' => $baseUrl . '/blog/' . $post->slug,
                'lastmod' => $post->updated_at?->toW3cString(),
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ]);
        }

        // Blog index
        if ($posts->isNotEmpty()) {
            $this->write(['loc' => $baseUrl . '/blog', 'changefreq' => 'weekly', 'priority' => '0.5']);
        }

        // CMS Pages (about-us, privacy-policy, payment-and-delivery, etc.)
        foreach (Page::where('is_active', true)->select(['id', 'slug', 'updated_at'])->lazyById(1000) as $page) {
            $this->write([
                'loc' => $baseUrl . '/' . $page->slug,
                'lastmod' => $page->updated_at?->toW3cString(),
                'changefreq' => 'monthly',
                'priority' => '0.4',
            ]);
        }

        // Contact page (statically routed)
        $this->write(['loc' => $baseUrl . '/contact', 'changefreq' => 'monthly', 'priority' => '0.3']);
    }

    /** @param array{loc: string, lastmod?: ?string, changefreq?: ?string, priority?: ?string} $url */
    private function write(array $url): void
    {
        if ($this->handle === null || $this->inFile >= self::PER_FILE) {
            $this->startFile();
        }

        $xml = "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
        if (! empty($url['lastmod'])) {
            $xml .= '    <lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
        }
        if (! empty($url['changefreq'])) {
            $xml .= '    <changefreq>' . htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
        }
        if (isset($url['priority']) && $url['priority'] !== '') {
            $xml .= '    <priority>' . htmlspecialchars($url['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
        }
        $xml .= "  </url>\n";

        $this->put($xml);
        $this->listed[$url['loc']] = true;
        $this->inFile++;
        $this->total++;
    }

    /** Close the numbered file being written, if any, and open the next one, as a temporary file until the end. */
    private function startFile(): void
    {
        $this->closeFile();
        $this->handlePath = $this->path . '.' . (count($this->files) + 1) . '.tmp';
        $handle = fopen($this->handlePath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('The sitemap file could not be opened for writing.');
        }
        $this->handle = $handle;
        $this->inFile = 0;
        $this->put('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");
    }

    private function closeFile(): void
    {
        if ($this->handle === null) {
            return;
        }
        $this->put('</urlset>');
        fclose($this->handle);
        $this->files[] = (string) $this->handlePath;
        $this->handle = null;
        $this->handlePath = null;
    }

    private function put(string $text): void
    {
        if (fwrite($this->handle, $text) !== strlen($text)) {
            throw new \RuntimeException('The sitemap file could not be written.');
        }
    }

    /**
     * Put the finished files in place. One file is the sitemap itself, as
     * it always was; more become numbered files beside an index. Numbered
     * files a bigger earlier sitemap left behind are removed.
     */
    private function finish(): void
    {
        $this->closeFile();
        $numbered = [];
        if (count($this->files) === 1) {
            $this->move($this->files[0], $this->path);
        } else {
            $index = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            $now = now()->toW3cString();
            foreach ($this->files as $i => $temporary) {
                $target = $this->numbered($i + 1);
                $this->move($temporary, $target);
                $numbered[] = $target;
                $index .= "  <sitemap>\n    <loc>" . htmlspecialchars($this->publicUrl($target), ENT_XML1, 'UTF-8') . "</loc>\n    <lastmod>{$now}</lastmod>\n  </sitemap>\n";
            }
            $index .= '</sitemapindex>';
            $temporary = $this->path . '.index.tmp';
            if (file_put_contents($temporary, $index) === false) {
                throw new \RuntimeException('The sitemap index could not be written.');
            }
            $this->move($temporary, $this->path);
        }
        $this->files = [];

        foreach (glob($this->numbered('*')) ?: [] as $file) {
            if (preg_match('/-\d+\.xml$/', $file) === 1 && ! in_array($file, $numbered, true)) {
                @unlink($file);
            }
        }
    }

    /** Remove the temporary files of a run that failed; the sitemap already in place stays. */
    private function abandon(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->files[] = (string) $this->handlePath;
            $this->handle = null;
        }
        foreach ($this->files as $file) {
            @unlink($file);
        }
        $this->files = [];
    }

    private function move(string $from, string $to): void
    {
        if (! rename($from, $to)) {
            throw new \RuntimeException('The sitemap file could not be put in place.');
        }
    }

    /** sitemap-{n}.xml beside the sitemap. */
    private function numbered(int|string $n): string
    {
        return substr($this->path, 0, -4) . '-' . $n . '.xml';
    }

    /** The address a numbered file is served at: its place under public/, else beside the sitemap at the site root. */
    private function publicUrl(string $file): string
    {
        $public = rtrim(public_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $relative = str_starts_with($file, $public) ? substr($file, strlen($public)) : basename($file);

        return $this->baseUrl . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
}
