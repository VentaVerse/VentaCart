<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_shell_renders_nav_groups_theme_boot_and_toasts(): void
    {
        $this->actingAs(User::factory()->create());

        $html = (string) $this->blade('<x-admin.shell title="Test">Hello content</x-admin.shell>');

        $this->assertStringContainsString('v-admin', $html);
        $this->assertStringContainsString('Hello content', $html);
        $this->assertStringContainsString('Catalog', $html);   // nav group
        $this->assertStringContainsString('vTheme', $html);     // theme boot
        $this->assertStringContainsString('$store.toasts', $html);
    }

    public function test_page_renders_title_breadcrumb_and_actions(): void
    {
        $this->blade('<x-admin.page title="Products" :breadcrumb="[[\'label\' => \'Catalog\']]">BODY<x-slot:actions>ACT</x-slot:actions></x-admin.page>')
            ->assertSeeInOrder(['Catalog', 'Products', 'ACT', 'BODY']);
    }
}
