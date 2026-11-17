<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Page;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tag;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use App\Support\StoreIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Rendering\Storefront;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The content pages (blog, blog post, information page, contact and page
 * not found) draw their templates from what core's controllers hand them,
 * keep core's forms and checks, and answer 404 the way core does.
 */
class XelgaContentPagesTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
    }

    private function blogOn(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->install('blog');
        $manager->bootEnabled();
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

    private function blogPost(array $attributes = []): \Extensions\Blog\Models\BlogPost
    {
        return \Extensions\Blog\Models\BlogPost::create($attributes + [
            'title' => 'How to wash linen',
            'slug' => 'wash-linen',
            'content' => '<p>Cold water and a line in the shade.</p>',
            'excerpt' => 'Cold water, no dryer.',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_the_blog_lists_published_posts_with_category_links_and_search(): void
    {
        $this->blogOn();
        $care = \Extensions\Blog\Models\BlogCategory::create(['name' => 'Care guides', 'slug' => 'care', 'sort_order' => 1]);
        $style = \Extensions\Blog\Models\BlogCategory::create(['name' => 'Style notes', 'slug' => 'style', 'sort_order' => 2]);
        $this->blogPost(['blog_category_id' => $care->id]);
        $this->blogPost(['title' => 'A capsule for humid months', 'slug' => 'capsule', 'blog_category_id' => $style->id, 'published_at' => now()->subDays(2)]);
        $this->blogPost(['title' => 'A post for next week', 'slug' => 'next-week', 'published_at' => now()->addWeek()]);
        $this->blogPost(['title' => 'A draft post', 'slug' => 'draft', 'is_published' => false]);

        $this->get('/blog')->assertOk()
            ->assertSee('data-section-type="blog-main"', false)
            ->assertSee('How to wash linen')
            ->assertSee('A capsule for humid months')
            ->assertSee('Cold water, no dryer.')
            ->assertSee('Care guides')
            ->assertSee('/blog?category=style"', false)
            ->assertSee('2 posts')
            ->assertDontSee('A post for next week')
            ->assertDontSee('A draft post')
            ->assertSee('data-section-type="image-with-text"', false);

        Storefront::forgetRequestState();
        $care = $this->get('/blog?category=care')->assertOk()
            ->assertSee('How to wash linen')
            ->assertDontSee('A capsule for humid months');
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Care guides/', $care->getContent());

        Storefront::forgetRequestState();
        $this->get('/blog?q=capsule')->assertOk()
            ->assertSee('found for &ldquo;capsule&rdquo;', false)
            ->assertSee('A capsule for humid months')
            ->assertDontSee('How to wash linen');
    }

    public function test_a_too_short_blog_search_shows_core_s_message(): void
    {
        $this->blogOn();
        $this->blogPost();

        $this->followingRedirects()->get('/blog?q=x')->assertOk()
            ->assertSee('Enter at least 2 characters to search.')
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_an_empty_blog_says_so(): void
    {
        $this->blogOn();

        $this->get('/blog')->assertOk()
            ->assertSee('0 posts')
            ->assertSee('No posts yet. Check back soon.');
    }

    public function test_the_blog_answers_404_with_the_theme_page_while_the_extension_is_off(): void
    {
        $this->get('/blog')->assertNotFound()->assertSee('data-section-type="not-found-main"', false);
    }

    public function test_a_blog_post_shows_its_text_category_and_tags_and_escapes_its_title(): void
    {
        $this->blogOn();
        $care = \Extensions\Blog\Models\BlogCategory::create(['name' => 'Care guides', 'slug' => 'care', 'sort_order' => 1]);
        $post = $this->blogPost(['title' => 'Linen <script>alert(1)</script> care', 'blog_category_id' => $care->id]);
        $tag = Tag::create(['name' => 'linen', 'slug' => 'linen']);
        $post->tags()->attach($tag->id);

        $this->get('/blog/wash-linen')->assertOk()
            ->assertSee('data-section-type="post-main"', false)
            ->assertSee('Linen &lt;script&gt;alert(1)&lt;/script&gt; care', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('<p>Cold water and a line in the shade.</p>', false)
            ->assertSee('/blog?tag=linen"', false)
            ->assertSee('/blog?category=care"', false)
            ->assertSee('Back to the blog')
            ->assertSee('data-section-type="blog-posts"', false);
    }

    public function test_an_unpublished_post_is_not_found(): void
    {
        $this->blogOn();
        $this->blogPost(['is_published' => false]);

        $this->get('/blog/wash-linen')->assertNotFound()->assertSee('data-section-type="not-found-main"', false);
    }

    public function test_an_information_page_shows_its_text_and_a_menu_of_the_other_pages(): void
    {
        Page::create(['store_id' => 1, 'title' => 'Shipping and delivery', 'slug' => 'shipping', 'body' => '<h2>Where we ship</h2><p>Everywhere.</p>', 'is_active' => true, 'sort_order' => 1]);
        Page::create(['store_id' => 1, 'title' => 'Payment options', 'slug' => 'payment', 'body' => '<p>Cash.</p>', 'is_active' => true, 'sort_order' => 2]);
        Page::create(['store_id' => 1, 'title' => 'Hidden page', 'slug' => 'hidden', 'body' => '<p>No.</p>', 'is_active' => false, 'sort_order' => 3]);

        $page = $this->get('/shipping')->assertOk()
            ->assertSee('data-section-type="page-main"', false)
            ->assertSee('Shipping and delivery')
            ->assertSee('<h2>Where we ship</h2>', false)
            ->assertSee('Payment options')
            ->assertDontSee('Hidden page')
            ->assertSee('Still have a question?');
        $this->assertMatchesRegularExpression('#/contact"\s*>Contact us</a>#', $page->getContent());
        $this->assertMatchesRegularExpression('#/shipping"\s+aria-current="page"\s*>Shipping and delivery#', $page->getContent());

        Storefront::forgetRequestState();
        $this->get('/hidden')->assertNotFound()->assertSee('data-section-type="not-found-main"', false);
    }

    public function test_the_contact_page_keeps_core_s_form_and_shows_the_store_s_details(): void
    {
        Store::query()->update(['phone' => '0917 000 0000', 'email' => 'hello@example.com', 'address' => 'Quezon City']);
        StoreIdentity::flush();

        $response = $this->get('/contact')->assertOk()
            ->assertSee('data-section-type="contact-main"', false)
            ->assertSee('/contact" method="post"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('hello@example.com')
            ->assertSee('href="tel:09170000000"', false)
            ->assertSee('Quezon City')
            ->assertSee('Demo Store');

        foreach (['name="name"', 'name="email"', 'name="subject"', 'name="message"'] as $field) {
            $response->assertSee($field, false);
        }
        $response->assertSee('<option value="Order question"', false);
    }

    public function test_the_contact_form_shows_core_s_errors_beside_the_fields_and_keeps_what_was_typed(): void
    {
        $this->from('/contact')->post('/contact', ['name' => 'Juan Dela Cruz', 'email' => 'not-an-email', 'subject' => '', 'message' => 'Hello'])
            ->assertRedirect('/contact')
            ->assertSessionHasErrors(['email', 'subject']);

        Storefront::forgetRequestState();
        $this->get('/contact')->assertOk()
            ->assertSee('Check the fields marked below, then send again.')
            ->assertSee('value="Juan Dela Cruz"', false)
            ->assertSee('value="not-an-email"', false)
            ->assertSee('The email field must be a valid email address.')
            ->assertSee('Hello</textarea>', false);
    }

    public function test_a_sent_message_shows_core_s_thank_you(): void
    {
        Mail::fake();

        $this->from('/contact')->followingRedirects()->post('/contact', [
            'name' => 'Maria Santos', 'email' => 'maria@example.com', 'subject' => 'Order question', 'message' => 'Where is my parcel?',
        ])->assertOk()->assertSee('Thank you for your message.');
    }

    public function test_a_missing_page_draws_the_theme_s_not_found_page_with_status_404(): void
    {
        $this->get('/no-such-page-anywhere')->assertNotFound()
            ->assertSee('data-section-type="not-found-main"', false)
            ->assertSee('This page has moved or no longer exists')
            ->assertSee('/search" method="get"', false)
            ->assertSee('data-section-type="header"', false)
            ->assertSee('noindex', false);
    }

    public function test_a_switched_off_product_answers_with_the_theme_s_not_found_page(): void
    {
        Product::create(['store_id' => Store::query()->value('id'), 'name' => 'Old dress', 'slug' => 'old-dress', 'price' => 1250, 'is_active' => false]);

        $this->get('/product/old-dress')->assertNotFound()->assertSee('data-section-type="not-found-main"', false);
    }

    public function test_a_missing_file_gets_core_s_small_404_and_a_missing_page_gets_the_store_s(): void
    {
        foreach (['/build/assets/gone.js', '/vendor/gone', '/wp-login.php', '/images/gone.png'] as $path) {
            Storefront::forgetRequestState();
            $this->get($path)->assertNotFound()->assertDontSee('data-section-type="not-found-main"', false);
        }
        // Core may answer for storage/ itself (403 for a missing local file); either way it is never the store page.
        Storefront::forgetRequestState();
        $this->assertTrue(\Themes\Xelga\Rendering\NotFoundPage::isFile(\Illuminate\Http\Request::create('/storage/products/gone')));
        $this->get('/storage/products/gone.jpg')->assertDontSee('data-section-type="not-found-main"', false);

        Storefront::forgetRequestState();
        $html = $this->get('/no-such-page-anywhere')->assertNotFound()
            ->assertSee('data-section-type="not-found-main"', false)->getContent();
        // The shipped page is light: no product feed is drawn for a miss.
        $this->assertStringNotContainsString('data-section-type="featured-products"', $html);

        // A version number in a slug is not a file extension.
        Storefront::forgetRequestState();
        $this->get('/product/size-2.5')->assertNotFound()->assertSee('data-section-type="not-found-main"', false);
    }

    public function test_json_callers_and_other_live_themes_keep_core_s_404(): void
    {
        $this->getJson('/no-such-page-anywhere')->assertNotFound()->assertDontSee('not-found-main');

        app(ThemeService::class)->activateTheme(\Tests\TestCase::SAMPLE_THEME);
        app(ThemeService::class)->clearCache();
        Storefront::forgetRequestState();

        $this->get('/no-such-page-anywhere')->assertNotFound()->assertDontSee('data-section-type="not-found-main"', false);
    }
}
