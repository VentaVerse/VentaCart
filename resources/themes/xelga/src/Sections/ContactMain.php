<?php

namespace Themes\Xelga\Sections;

use App\Support\StoreIdentity;
use Illuminate\Support\Str;
use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;

/**
 * The contact page: the store's email, phone and address from its record
 * in VentaCart, the opening hours from the theme settings, and the topics
 * the message form offers. The form itself posts to core's contact route
 * with core's field names, so core's checks, captcha and rate limit apply.
 */
final class ContactMain implements SectionData
{
    /** Most topics the form offers. */
    private const TOPICS_MAX = 12;

    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        $email = StoreIdentity::email();
        $phone = self::clean(StoreIdentity::phone());

        $topics = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 'form') {
                $topics[$block['id']] = self::topics((string) ($block['settings']['topics'] ?? ''));
            }
        }

        return [
            'crumbs' => Breadcrumbs::trail($context),
            'storeName' => StoreIdentity::name(),
            'details' => [
                'email' => $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
                'phone' => $phone,
                'tel' => $phone !== null ? (string) preg_replace('/[^0-9+]/', '', $phone) : '',
                'address' => self::clean(StoreIdentity::address()),
                'hours' => self::clean(ThemeValues::get('hours')),
            ],
            'topics' => $topics,
            'action' => route('contact.submit'),
        ];
    }

    /**
     * The topics, one per line, trimmed and without repeats. Core accepts any
     * subject up to 255 characters, so each is cut to fit.
     *
     * @return list<string>
     */
    public static function topics(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = Str::limit(trim($line), 255, '');
            if ($line !== '' && ! in_array($line, $out, true)) {
                $out[] = $line;
            }
        }

        return array_slice($out, 0, self::TOPICS_MAX);
    }

    private static function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
