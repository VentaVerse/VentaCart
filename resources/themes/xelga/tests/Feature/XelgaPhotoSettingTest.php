<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * A block type may name the image setting that Add photos fills. The lint
 * accepts a name that matches an image setting and refuses any other.
 */
class XelgaPhotoSettingTest extends TestCase
{
    private const WRONG = 'Section zz-probe block photo: photo_setting must name an image setting of the block.';

    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
    }

    /** @param array<string, mixed> $type */
    private function problemsWith(array $type): array
    {
        $schema = Catalog::root('schemas/sections/zz-probe.json');
        file_put_contents($schema, json_encode([
            'type' => 'zz-probe', 'label' => 'Probe', 'group' => 'template', 'templates' => ['home'],
            'class' => 's-zz-probe', 'picker_group' => 'text', 'settings' => [],
            'blocks' => ['min' => 0, 'max' => 4, 'types' => [$type + [
                'type' => 'photo', 'label' => 'Photo',
                'settings' => [
                    ['key' => 'image', 'type' => 'image', 'label' => 'Photo', 'default' => ''],
                    ['key' => 'caption', 'type' => 'text', 'label' => 'Caption', 'max' => 80, 'default' => ''],
                ],
            ]]],
        ]));
        try {
            return CatalogLinter::check();
        } finally {
            @unlink($schema);
            Catalog::flush();
        }
    }

    public function test_the_lint_accepts_a_photo_setting_that_names_an_image_setting(): void
    {
        $this->assertNotContains(self::WRONG, $this->problemsWith(['photo_setting' => 'image']));
        $this->assertNotContains(self::WRONG, $this->problemsWith([]));
    }

    public function test_the_lint_refuses_a_photo_setting_that_is_missing_or_not_an_image(): void
    {
        $this->assertContains(self::WRONG, $this->problemsWith(['photo_setting' => 'nothing']));
        $this->assertContains(self::WRONG, $this->problemsWith(['photo_setting' => 'caption']));
        $this->assertContains(self::WRONG, $this->problemsWith(['photo_setting' => ['image']]));
        $this->assertSame([], CatalogLinter::check());
    }
}
