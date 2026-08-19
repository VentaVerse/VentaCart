<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Owner ruling, 2026-08-20: a preset a merchant saves must never be written
 * into the repo.
 *
 * It was. saveCurrentAsPreset() wrote into resources/themes/{theme}/presets,
 * which is a tracked path and part of the deployment artifact, so one preset
 * caused three separate problems: a `git add -A` would publish the merchant's
 * private looks to everyone who downloads Venta, a deploy would delete them
 * because that folder is replaced from git, and on a hosted install one tenant
 * would be writing into the shared image. Two such files sat untracked in the
 * working tree for a whole day before anyone noticed.
 *
 * The split is by ownership, not by kind. Presets Venta ships are product
 * content and stay in resources, read-only. Presets a merchant makes are their
 * data and live in storage, which is gitignored and persists across deploys,
 * the same place the migrated catalogue images already live.
 */
class PresetStorageLocationTest extends TestCase
{
    use RefreshDatabase;

    private ThemeService $theme;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_theme'], ['value' => 'venta']);
        $this->theme = app(ThemeService::class);
        File::deleteDirectory(storage_path('app/themes'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/themes'));
        parent::tearDown();
    }

    private function shippedDir(): string
    {
        return resource_path('themes/venta/presets');
    }

    private function userDir(): string
    {
        return storage_path('app/themes/venta/presets');
    }

    public function test_saving_a_preset_writes_to_storage_and_never_to_the_repo(): void
    {
        $before = glob($this->shippedDir() . '/*.json');

        $slug = $this->theme->saveCurrentAsPreset('My Shop Look');

        $this->assertFileExists($this->userDir() . "/{$slug}.json");
        $this->assertFileDoesNotExist($this->shippedDir() . "/{$slug}.json");
        $this->assertSame(
            $before,
            glob($this->shippedDir() . '/*.json'),
            'Saving a preset changed the contents of a tracked repo directory.'
        );
    }

    public function test_the_directory_it_writes_to_is_gitignored(): void
    {
        $this->theme->saveCurrentAsPreset('Ignored Look');

        $rel = 'storage/app/themes/venta/presets/ignored-look.json';
        exec('cd ' . escapeshellarg(base_path()) . ' && git check-ignore ' . escapeshellarg($rel), $out, $code);

        $this->assertSame(0, $code, "{$rel} is NOT gitignored, so a merchant's preset could be committed.");
    }

    public function test_the_list_shows_shipped_and_saved_presets_together(): void
    {
        $this->theme->saveCurrentAsPreset('Mine Only');

        $presets = $this->theme->getInstalledPresets();

        $this->assertArrayHasKey('mine-only', $presets, 'A saved preset is missing from the list.');
        $this->assertArrayHasKey('stockroom', $presets, 'A shipped preset is missing from the list.');
    }

    /** Customise "Stockroom" and keep calling it that, without losing the original. */
    public function test_a_saved_preset_overrides_a_shipped_one_of_the_same_name(): void
    {
        File::ensureDirectoryExists($this->userDir());
        file_put_contents($this->userDir() . '/stockroom.json', json_encode([
            'name' => 'Stockroom, my way',
            'colors' => ['primary' => '#123456'],
        ]));

        $presets = $this->theme->getInstalledPresets();

        $this->assertSame('Stockroom, my way', $presets['stockroom']['name']);
    }

    public function test_a_saved_preset_can_be_deleted(): void
    {
        $slug = $this->theme->saveCurrentAsPreset('Throwaway');
        $this->assertFileExists($this->userDir() . "/{$slug}.json");

        $this->theme->deletePreset($slug);

        $this->assertFileDoesNotExist($this->userDir() . "/{$slug}.json");
    }

    /** A shipped preset is product content. Deleting it would edit the repo. */
    public function test_a_shipped_preset_cannot_be_deleted(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'active_preset'], ['value' => 'standard']);

        $this->expectException(\InvalidArgumentException::class);

        $this->theme->deletePreset('stockroom');

        $this->assertFileExists($this->shippedDir() . '/stockroom.json');
    }
}
