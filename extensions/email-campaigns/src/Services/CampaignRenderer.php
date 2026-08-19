<?php

namespace Extensions\EmailCampaigns\Services;

use App\Models\Customer;
use App\Support\StoreIdentity;
use Illuminate\Support\Facades\URL;

/**
 * Placeholder substitution for a campaign body or subject.
 *
 * Uses core's existing {key} convention rather than inventing one: the shipped
 * order confirmation already reads "Hi {customer_name}". The helper is kept
 * local to this extension rather than widening EmailTemplate, because a
 * campaign body is not an EmailTemplate row and the two have different token
 * sets.
 *
 * A token the author invented is left in place rather than blanked, so a typo
 * is visible in the test send instead of silently deleting a sentence.
 */
class CampaignRenderer
{
    public static function render(string $text, Customer $customer): string
    {
        return self::renderWith($text, self::valuesFor($customer));
    }

    /**
     * Substitute an explicit token map. The admin test-send has no Customer to
     * render against (an admin is not a customer), so it supplies its own
     * values and reuses this loop rather than copying it.
     *
     * @param array<string, string> $values
     */
    public static function renderWith(string $text, array $values): string
    {
        foreach ($values as $token => $value) {
            $text = str_replace('{' . $token . '}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * @return array<string, string> token => value
     *
     * store_name comes from StoreIdentity rather than a direct Setting
     * query: StoreIdentity memoizes for the life of the process, so a send
     * that calls render() twice per recipient (subject, then body) resolves
     * the store's name once for the whole run instead of once per call.
     */
    private static function valuesFor(Customer $customer): array
    {
        return [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'store_name' => StoreIdentity::name(),
            'unsubscribe_url' => URL::signedRoute('newsletter.unsubscribe', ['customer' => $customer->id]),
        ];
    }

    /**
     * What the compose toolbar offers. Label is what the button says; the key
     * is what gets inserted at the cursor.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        return [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'store_name' => 'Store name',
            'unsubscribe_url' => 'Unsubscribe link',
        ];
    }
}
