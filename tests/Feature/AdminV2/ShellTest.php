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

    // The x-admin.page primitive retired with the v2 layer; the v3
    // pages carry their own head band (v3-head / v3-day__head).
}
