<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Defaults are platform-neutral. Sites pick their own currency/timezone
        // via .env (STORE_CURRENCY, APP_TIMEZONE) before first seeding, or by
        // editing the store record afterwards.
        // firstOrCreate: the store's name, currency and timezone are set in
        // the admin, not in .env, and an upgrade must never pull them back to
        // whatever the environment happens to say.
        \App\Models\Store::firstOrCreate(['id' => 1], [
            'name' => config('app.name', 'Venta Store'),
            'code' => 'default',
            'domain' => parse_url(config('app.url', ''), PHP_URL_HOST) ?: 'localhost',
            'currency' => env('STORE_CURRENCY', 'USD'),
            'timezone' => config('app.timezone', 'UTC'),
            'is_active' => true,
        ]);
    }
}
