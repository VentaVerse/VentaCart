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
        \App\Models\Store::create([
            'name' => 'New Gear Day',
            'code' => 'ngd',
            'domain' => 'newgearday.com',
            'currency' => 'PHP',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ]);

        \App\Models\Store::create([
            'name' => 'Dragon Switch',
            'code' => 'ds',
            'domain' => 'dragon-switch.com',
            'currency' => 'PHP',
            'timezone' => 'Asia/Manila',
            'is_active' => true,
        ]);
    }
}
