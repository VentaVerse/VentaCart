<?php

namespace Themes\Xelga\Sections;

use App\Support\NavTree;
use App\Support\PaymentFamilies;
use App\Support\StoreIdentity;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/**
 * The footer's data: menus as core composes them, the store's contact
 * details from Settings, the social links and opening hours from the theme
 * settings, and the payment methods the store has switched on.
 */
final class Footer implements SectionData
{
    /** The social networks the footer draws, in order, with their setting. */
    public const SOCIALS = [
        'facebook' => ['setting' => 'facebook_url', 'label' => 'Facebook'],
        'instagram' => ['setting' => 'instagram_url', 'label' => 'Instagram'],
        'tiktok' => ['setting' => 'tiktok_url', 'label' => 'TikTok'],
        'youtube' => ['setting' => 'youtube_url', 'label' => 'YouTube'],
    ];

    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $menus = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 'menu') {
                $key = (string) ($block['settings']['menu'] ?? '');
                $menus[$block['id']] = $key !== '' ? NavTree::for($key) : [];
            }
        }

        $theme = ThemeValues::all();
        $socials = [];
        foreach (self::SOCIALS as $network => $social) {
            $url = LinkValue::resolve($theme[$social['setting']] ?? null);
            if ($url !== null && preg_match('#^https?://#i', $url) === 1) {
                $socials[] = ['network' => $network, 'label' => $social['label'], 'url' => $url];
            }
        }

        $name = StoreIdentity::name();
        $legal = trim((string) ($settings['legal'] ?? ''));
        $email = StoreIdentity::email();

        return [
            'storeName' => $name,
            'menus' => $menus,
            'contact' => [
                'phone' => $this->clean(StoreIdentity::phone()),
                'email' => $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                'address' => $this->clean(StoreIdentity::address()),
                'hours' => $this->clean($theme['hours'] ?? null),
            ],
            'payments' => array_values(array_map(fn (array $f) => (string) $f['default_name'], PaymentFamilies::enabledFamilies())),
            'socials' => $socials,
            'legal' => $legal !== '' ? $legal : '© ' . now()->year . ' ' . $name,
        ];
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
