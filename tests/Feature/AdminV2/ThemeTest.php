<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class ThemeTest extends TestCase
{
    public function test_theme_boot_sets_data_theme_before_paint(): void
    {
        $this->blade('<x-admin.theme-boot />')
            ->assertSee('vTheme', false)
            ->assertSee('data-theme', false)
            ->assertSee('prefers-color-scheme', false);
    }

    public function test_theme_toggle_cycles_three_states(): void
    {
        $this->blade('<x-admin.theme-toggle />')
            ->assertSee('system', false)->assertSee('light', false)->assertSee('dark', false);
    }
}
