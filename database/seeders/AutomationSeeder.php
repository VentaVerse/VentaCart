<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Services\CronExpressionBuilder;
use Illuminate\Database\Seeder;

class AutomationSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate (NOT updateOrCreate) is deliberate: once a row exists,
        // the admin owns it. Re-seeding on upgrade must never overwrite their
        // edited schedules or enabled toggles.
        foreach (self::defaults() as $row) {
            $row['cron_expression'] = CronExpressionBuilder::build(
                $row['schedule_preset'],
                $row['schedule_params']
            );
            Automation::firstOrCreate(['key' => $row['key']], $row);
        }
    }

    /**
     * The seven default Venta automations. Admins can edit their schedule and
     * enabled flag in Settings > Automations after seeding.
     */
    private static function defaults(): array
    {
        return [
            [
                'key' => 'currency_update',
                'name' => 'Currency Exchange Rates',
                'command' => 'currency:update',
                'description' => 'Fetches daily exchange rates from the ECB feed and updates the currency table.',
                'schedule_preset' => 'daily',
                'schedule_params' => ['hour' => 16, 'minute' => 0],
                'enabled' => true,
            ],
            [
                'key' => 'stock_notify',
                'name' => 'Back-in-Stock Notifications',
                'command' => 'stock:notify',
                'description' => 'Emails customers when products they subscribed to become available.',
                'schedule_preset' => 'every_n_minutes',
                'schedule_params' => ['minute' => 15],
                'enabled' => true,
            ],
            [
                'key' => 'review_send',
                'name' => 'Review Request Emails',
                'command' => 'review:send',
                'description' => 'Sends post-purchase review request emails to customers whose orders are complete.',
                'schedule_preset' => 'daily',
                'schedule_params' => ['hour' => 10, 'minute' => 0],
                'enabled' => true,
            ],
            [
                'key' => 'seo_generate',
                'name' => 'SEO Suggestions (Claude AI)',
                'command' => 'seo:generate',
                'description' => 'Generates AI-assisted SEO metadata suggestions for products lacking optimized titles and descriptions.',
                'schedule_preset' => 'monthly',
                'schedule_params' => ['day_of_month' => 1, 'hour' => 3, 'minute' => 0],
                'enabled' => true,
            ],
            [
                'key' => 'sitemap_generate',
                'name' => 'Sitemap Generation',
                'command' => 'sitemap:generate',
                'description' => 'Rebuilds /sitemap.xml with the latest products, categories, and blog posts.',
                'schedule_preset' => 'daily',
                'schedule_params' => ['hour' => 4, 'minute' => 0],
                'enabled' => true,
            ],
            [
                'key' => 'stock_check_low',
                'name' => 'Low Stock Alerts',
                'command' => 'stock:check-low',
                'description' => 'Emails an admin digest of products whose stock fell below the configured low-stock threshold.',
                'schedule_preset' => 'daily',
                'schedule_params' => ['hour' => 8, 'minute' => 0],
                'enabled' => true,
            ],
            [
                'key' => 'cart_notify',
                'name' => 'Abandoned Cart Notifications',
                'command' => 'cart:notify',
                'description' => 'Emails reminders to customers whose carts match configured abandonment rules.',
                'schedule_preset' => 'hourly',
                'schedule_params' => ['minute' => 0],
                'enabled' => true,
            ],
            self::queueRow(),
            [
                'key' => 'cart_prune_empty',
                'name' => 'Prune Empty Guest Carts',
                'command' => 'cart:prune-empty',
                'description' => 'Deletes guest carts with no items that have been untouched for over 24 hours. Safety net for the cart leak fix.',
                'schedule_preset' => 'daily',
                'schedule_params' => ['hour' => 3, 'minute' => 0],
                'enabled' => true,
            ],
        ];
    }

    /**
     * Sends what is waiting in the queue: status emails and texts.
     *
     * Notifications are queued so a courier's webhook or the ERP's call is
     * never held open by a slow gateway. Pterodactyl and Docker installs keep
     * a worker running; a plain web host (HestiaCP, cPanel) has only cron. This
     * row is the cron's way of emptying the queue every minute, so a store
     * sends without a separate worker. Where a worker does run it simply finds
     * the queue already empty. Public so the migration that adds it to stores
     * installed before it existed uses the same definition.
     */
    public static function queueRow(): array
    {
        return [
            'key' => 'queue_work',
            'name' => 'Send Queued Emails and Texts',
            'command' => 'queue:work --stop-when-empty --max-time=50',
            'description' => 'Sends order status emails and SMS waiting in the queue. Stores without a separate queue worker rely on this to send at all.',
            'schedule_preset' => 'every_n_minutes',
            'schedule_params' => ['minute' => 1],
            'enabled' => true,
        ];
    }
}
