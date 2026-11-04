<?php

namespace Tests\Feature\Storefront;

use App\Services\ExtensionManager;
use Extensions\Blog\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The blog listing's text search: it reads the title, excerpt and body of
 * the posts a visitor may read, binds every word as a parameter and refuses
 * searches that are too short or too long.
 */
class BlogSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ExtensionManager::class)->install('blog');
        app(ExtensionManager::class)->bootEnabled();
        $this->putPageRoutesLast();
    }

    /**
     * On a store the blog boots before core's routes, so /blog is matched
     * before the page catch-all. Booted inside a test it is added after, so
     * the catch-all and the fallback are moved back to the end.
     */
    private function putPageRoutesLast(): void
    {
        $router = app('router');
        $first = new \Illuminate\Routing\RouteCollection();
        $last = [];

        foreach ($router->getRoutes() as $route) {
            if ($route->getName() === 'page.show' || $route->isFallback) {
                $last[] = $route;
            } else {
                $first->add($route);
            }
        }
        foreach ($last as $route) {
            $first->add($route);
        }

        $router->setRoutes($first);
    }

    private function blogPost(string $title, array $extra = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title) . '-' . \Illuminate\Support\Str::random(4),
            'content' => '<p>Plain body.</p>',
            'excerpt' => null,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ], $extra));
    }

    public function test_a_search_finds_posts_by_title_excerpt_and_body(): void
    {
        $this->blogPost('Choosing a keyboard');
        $this->blogPost('Care guide', ['excerpt' => 'How to clean your keyboard']);
        $this->blogPost('Desk setup', ['content' => '<p>A tidy keyboard tray.</p>']);
        $this->blogPost('Shipping news');

        $this->get(route('blog.index', ['q' => 'keyboard']))
            ->assertOk()
            ->assertSee('Choosing a keyboard')
            ->assertSee('Care guide')
            ->assertSee('Desk setup')
            ->assertDontSee('Shipping news')
            ->assertSee('3 posts found for', false);
    }

    public function test_every_word_must_match(): void
    {
        $this->blogPost('Wireless keyboard review');
        $this->blogPost('Wired keyboard review');

        $this->get(route('blog.index', ['q' => '  wireless   keyboard ']))
            ->assertOk()
            ->assertSee('Wireless keyboard review')
            ->assertDontSee('Wired keyboard review');
    }

    public function test_a_search_with_no_match_says_so(): void
    {
        $this->blogPost('Choosing a keyboard');

        $this->get(route('blog.index', ['q' => 'monitor']))
            ->assertOk()
            ->assertDontSee('Choosing a keyboard')
            ->assertSee('No posts match your search.');
    }

    public function test_unpublished_scheduled_and_undated_posts_are_left_out(): void
    {
        $this->blogPost('Keyboard draft', ['is_published' => false]);
        $this->blogPost('Keyboard scheduled', ['published_at' => now()->addDay()]);
        $this->blogPost('Keyboard undated', ['published_at' => null]);
        $this->blogPost('Keyboard live');

        $this->get(route('blog.index', ['q' => 'keyboard']))
            ->assertOk()
            ->assertSee('Keyboard live')
            ->assertDontSee('Keyboard draft')
            ->assertDontSee('Keyboard scheduled')
            ->assertDontSee('Keyboard undated');
    }

    public function test_wildcards_and_quotes_match_themselves(): void
    {
        $this->blogPost('Save 50% today');
        $this->blogPost('Save 50 dollars');
        $this->blogPost('snake_case names');
        $this->blogPost('snakeXcase names');

        $this->get(route('blog.index', ['q' => '50%']))
            ->assertOk()
            ->assertSee('Save 50% today')
            ->assertDontSee('Save 50 dollars');

        $this->get(route('blog.index', ['q' => 'snake_case']))
            ->assertOk()
            ->assertSee('snake_case names')
            ->assertDontSee('snakeXcase names');

        // A lone wildcard is two characters of nothing: it matches no post.
        $this->get(route('blog.index', ['q' => '%%']))
            ->assertOk()
            ->assertDontSee('Save 50 dollars')
            ->assertSee('No posts match your search.');
    }

    public function test_injection_like_input_is_only_text(): void
    {
        $this->blogPost('Keyboard live');
        $this->blogPost('Keyboard draft', ['is_published' => false]);

        foreach (["' OR 1=1 --", "keyboard' OR is_published = 0 --", '"); DROP TABLE blog_posts; --', "!' ESCAPE '"] as $q) {
            $this->get(route('blog.index', ['q' => $q]))
                ->assertOk()
                ->assertDontSee('Keyboard draft')
                ->assertDontSee('Keyboard live');
        }

        $this->assertSame(2, BlogPost::count());
    }

    public function test_the_search_is_escaped_on_the_page(): void
    {
        $this->get(route('blog.index', ['q' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_an_empty_search_shows_the_whole_blog(): void
    {
        $this->blogPost('Choosing a keyboard');
        $this->blogPost('Shipping news');

        foreach (['', '   '] as $q) {
            $this->get(route('blog.index', ['q' => $q]))
                ->assertOk()
                ->assertSee('Choosing a keyboard')
                ->assertSee('Shipping news')
                ->assertDontSee('found for', false);
        }
    }

    public function test_a_search_that_is_too_short_or_too_long_goes_back_to_the_blog(): void
    {
        $short = $this->get(route('blog.index', ['q' => 'a', 'category' => 'news']));
        $short->assertRedirect(route('blog.index', ['category' => 'news']));
        $short->assertSessionHasErrors(['q' => 'Enter at least 2 characters to search.']);

        $this->get(route('blog.index', ['q' => str_repeat('k', BlogPost::SEARCH_MAX + 1)]))
            ->assertRedirect(route('blog.index'))
            ->assertSessionHasErrors('q');

        $this->get(route('blog.index', ['q' => ['keyboard']]))
            ->assertRedirect(route('blog.index'))
            ->assertSessionHasErrors('q');

        // The redirect never keeps a value that fails its own rule, so it cannot loop.
        $this->get(route('blog.index', ['q' => 'a', 'view' => str_repeat('v', 30)]))
            ->assertRedirect(route('blog.index'));
    }

    public function test_results_are_paginated_and_keep_the_search(): void
    {
        for ($i = 1; $i <= 14; $i++) {
            $this->blogPost("Keyboard tip {$i}", ['published_at' => now()->subDays($i)]);
        }
        $this->blogPost('Shipping news');

        $this->get(route('blog.index', ['q' => 'keyboard']))
            ->assertOk()
            ->assertSee('14 posts found for', false)
            ->assertSee('q=keyboard', false)
            ->assertSee('page=2', false);

        $this->get(route('blog.index', ['q' => 'keyboard', 'page' => 2]))
            ->assertOk()
            ->assertSee('Keyboard tip 14')
            ->assertDontSee('Shipping news');
    }

    public function test_search_results_are_not_indexed(): void
    {
        $this->get(route('blog.index', ['q' => 'keyboard']))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_the_listing_without_a_search_stays_indexed(): void
    {
        $this->get(route('blog.index'))
            ->assertOk()
            ->assertDontSee('<meta name="robots" content="noindex, follow">', false);
    }
}
