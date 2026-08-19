<?php

namespace Extensions\EmailCampaigns\Audiences;

use InvalidArgumentException;

class AudienceRegistry
{
    /** @var array<string, class-string<Audience>> */
    private const TYPES = [
        'group' => CustomerGroupAudience::class,
        'subscribers' => NewsletterSubscribersAudience::class,
        'all' => AllCustomersAudience::class,
        'selected' => SelectedCustomersAudience::class,
    ];

    public static function for(string $type): Audience
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException("Unknown audience type [{$type}].");
        }

        return app(self::TYPES[$type]);
    }

    /** @return array<string, string> type => label, for the compose screen's select */
    public static function all(): array
    {
        $out = [];
        foreach (self::TYPES as $type => $class) {
            $out[$type] = app($class)->label();
        }

        return $out;
    }
}
