<?php

namespace Tests\Feature\Content;

use App\Models\Category;
use App\Models\Page;
use App\Models\User;
use App\Support\RichText;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product descriptions, category descriptions, pages and blog posts print
 * as HTML in every theme. Raw HTML is the storefront.code permission; a
 * Store Manager's edits keep normal formatting and lose anything that runs.
 */
class RichTextTest extends TestCase
{
    use RefreshDatabase;

    private const HOSTILE = '<p>Hello <strong>world</strong></p>'
        . '<script>alert(1)</script>'
        . '<img src="/storage/a.jpg" alt="A" onerror="alert(2)">'
        . '<a href="javascript:alert(3)">bad</a>'
        . "<a href=\"java\tscript:alert(4)\">tab</a>"
        . '<iframe src="https://evil.example/x"></iframe>'
        . '<div style="position:fixed;inset:0;background:url(//evil.example/t);color:#c00">x</div>'
        . '<svg onload="alert(5)"></svg>';

    private function manager(): User
    {
        $this->seed(RolePermissionSeeder::class);

        return User::factory()->role('store-manager')->create();
    }

    public function test_normal_formatting_survives(): void
    {
        $html = '<h2>Specs</h2><p style="text-align: center">Centred <em>text</em> and <a href="/sale" target="_blank">a link</a></p>'
            . '<ul><li>One</li></ul><ol><li>Two</li></ol><blockquote>Quote</blockquote>'
            . '<table><thead><tr><th>Size</th></tr></thead><tbody><tr><td colspan="2">M</td></tr></tbody></table>'
            . '<img src="/storage/products/a.jpg" alt="Front" width="300"><a href="https://example.com/guide">Guide</a>';

        $clean = RichText::sanitize($html);

        foreach (['<h2>Specs</h2>', 'style="text-align: center"', '<em>text</em>', 'href="/sale"', '<ul><li>One</li></ul>', '<blockquote>Quote</blockquote>', '<th>Size</th>', 'colspan="2"', 'src="/storage/products/a.jpg"', 'alt="Front"', 'href="https://example.com/guide"'] as $kept) {
            $this->assertStringContainsString($kept, $clean, $kept);
        }
    }

    public function test_hostile_html_is_cleaned(): void
    {
        $clean = RichText::sanitize(self::HOSTILE);

        $this->assertStringContainsString('<strong>world</strong>', $clean);
        $this->assertStringContainsString('src="/storage/a.jpg"', $clean);
        // A style that tries to load anything is dropped whole; one that
        // only formats keeps its formatting properties.
        $this->assertStringContainsString('<div>x</div>', $clean);
        $this->assertSame('<p style="color: #c00">y</p>', RichText::sanitize('<p style="position:fixed;color:#c00;z-index:9">y</p>'));
        foreach (['<script', 'alert', 'onerror', 'javascript', 'iframe', 'evil.example', 'position', '<svg'] as $gone) {
            $this->assertStringNotContainsString($gone, $clean, $gone);
        }
    }

    public function test_a_store_manager_saving_a_page_keeps_formatting_and_loses_script(): void
    {
        $this->actingAs($this->manager());

        $this->post(route('admin.pages.store'), ['title' => 'About', 'slug' => 'about', 'body' => self::HOSTILE, 'is_active' => 1])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $body = Page::where('slug', 'about')->firstOrFail()->body;
        $this->assertStringContainsString('<strong>world</strong>', $body);
        $this->assertStringNotContainsString('alert', $body);

        $this->get('/about')->assertOk()->assertDontSee('alert(', false);
    }

    public function test_a_super_admin_keeps_raw_html(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.pages.store'), ['title' => 'Embed', 'slug' => 'embed', 'body' => '<p>x</p><script>trackSale()</script>', 'is_active' => 1])->assertRedirect();

        $this->assertSame('<p>x</p><script>trackSale()</script>', Page::where('slug', 'embed')->firstOrFail()->body);
    }

    public function test_untouched_content_is_left_as_written(): void
    {
        // Written by a super admin (or before this check existed).
        $page = Page::create(['store_id' => 1, 'title' => 'Embed', 'slug' => 'embed', 'body' => '<p>x</p><script>trackSale()</script>', 'is_active' => true]);

        $this->actingAs($this->manager());
        $this->put(route('admin.pages.update', $page), ['title' => 'Embed renamed', 'slug' => 'embed', 'body' => $page->body, 'is_active' => 1])->assertRedirect();

        $page->refresh();
        $this->assertSame('Embed renamed', $page->title);
        $this->assertSame('<p>x</p><script>trackSale()</script>', $page->body);
    }

    public function test_commands_and_jobs_are_trusted(): void
    {
        $category = Category::create(['store_id' => 1, 'name' => 'Demo', 'slug' => 'demo', 'description' => '<p onclick="go()">x</p>', 'is_active' => true]);

        $this->assertSame('<p onclick="go()">x</p>', $category->fresh()->description);
    }

    public function test_embeds_from_listed_hosts_only(): void
    {
        config(['content.embed_hosts' => 'player.example.com']);

        $clean = RichText::sanitize('<iframe src="https://player.example.com/embed/1" width="560"></iframe><iframe src="https://evil.example/x"></iframe><iframe src="http://player.example.com/embed/2"></iframe>');

        $this->assertStringContainsString('src="https://player.example.com/embed/1"', $clean);
        $this->assertStringContainsString('sandbox=', $clean);
        $this->assertStringNotContainsString('evil.example', $clean);
        $this->assertStringNotContainsString('embed/2', $clean);
    }
}
