<?php

namespace Tests\Feature\EmailCampaigns;

use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExtensionInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_extension_ships_not_installed(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\DB::table('extensions')->where('name', 'email-campaigns')->exists(),
            'email-campaigns must not be installed on a fresh install.'
        );
    }

    public function test_it_is_offered_in_the_directory(): void
    {
        $this->assertContains('email-campaigns', app(ExtensionManager::class)->discovered()->keys()->all());
    }

    public function test_installing_it_creates_its_tables(): void
    {
        app(ExtensionManager::class)->install('email-campaigns');

        $this->assertTrue(Schema::hasTable('email_campaigns'));
        $this->assertTrue(Schema::hasTable('email_campaign_templates'));
    }

    public function test_its_nav_pointer_appears_only_once_installed(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.email-campaigns.index'));

        app(ExtensionManager::class)->install('email-campaigns');
        app(ExtensionManager::class)->bootEnabled();

        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.email-campaigns.index'));
    }
}
