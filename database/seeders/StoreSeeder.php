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
        \App\Models\Store::updateOrCreate(['id' => 1], [
            'name' => config('app.name', 'Venta Store'),
            'code' => 'default',
            'domain' => parse_url(config('app.url', ''), PHP_URL_HOST) ?: 'localhost',
            'currency' => 'PHP',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ]);
    }
}
