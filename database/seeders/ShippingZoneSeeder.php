<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use App\Models\ShippingZoneRate;
use App\Models\ShippingZoneRegion;
use Illuminate\Database\Seeder;

class ShippingZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'Metro Manila',
                'is_default' => false,
                'sort_order' => 1,
                'region_ids' => [13], // NCR
                'rates' => [
                    ['weight_from' => 0, 'weight_to' => 1, 'cost' => 100],
                    ['weight_from' => 1, 'weight_to' => 3, 'cost' => 150],
                    ['weight_from' => 3, 'weight_to' => 5, 'cost' => 200],
                    ['weight_from' => 5, 'weight_to' => 10, 'cost' => 300],
                ],
            ],
            [
                'name' => 'Luzon',
                'is_default' => false,
                'sort_order' => 2,
                'region_ids' => [1, 2, 3, 4, 5, 14, 17], // Regions I-V, CAR, MIMAROPA
                'rates' => [
                    ['weight_from' => 0, 'weight_to' => 1, 'cost' => 150],
                    ['weight_from' => 1, 'weight_to' => 3, 'cost' => 200],
                    ['weight_from' => 3, 'weight_to' => 5, 'cost' => 300],
                    ['weight_from' => 5, 'weight_to' => 10, 'cost' => 450],
                ],
            ],
            [
                'name' => 'Visayas',
                'is_default' => false,
                'sort_order' => 3,
                'region_ids' => [6, 7, 8], // Regions VI-VIII
                'rates' => [
                    ['weight_from' => 0, 'weight_to' => 1, 'cost' => 200],
                    ['weight_from' => 1, 'weight_to' => 3, 'cost' => 300],
                    ['weight_from' => 3, 'weight_to' => 5, 'cost' => 400],
                    ['weight_from' => 5, 'weight_to' => 10, 'cost' => 550],
                ],
            ],
            [
                'name' => 'Mindanao',
                'is_default' => true,
                'sort_order' => 4,
                'region_ids' => [9, 10, 11, 12, 15, 16], // Regions IX-XIII, ARMM, Caraga
                'rates' => [
                    ['weight_from' => 0, 'weight_to' => 1, 'cost' => 200],
                    ['weight_from' => 1, 'weight_to' => 3, 'cost' => 300],
                    ['weight_from' => 3, 'weight_to' => 5, 'cost' => 400],
                    ['weight_from' => 5, 'weight_to' => 10, 'cost' => 550],
                ],
            ],
        ];

        foreach ($zones as $zoneData) {
            $zone = ShippingZone::create([
                'store_id' => 1,
                'name' => $zoneData['name'],
                'is_default' => $zoneData['is_default'],
                'sort_order' => $zoneData['sort_order'],
                'status' => true,
            ]);

            foreach ($zoneData['region_ids'] as $regionId) {
                ShippingZoneRegion::create([
                    'shipping_zone_id' => $zone->id,
                    'ph_region_id' => $regionId,
                ]);
            }

            foreach ($zoneData['rates'] as $rate) {
                ShippingZoneRate::create([
                    'shipping_zone_id' => $zone->id,
                    'customer_group_id' => null,
                    'weight_from' => $rate['weight_from'],
                    'weight_to' => $rate['weight_to'],
                    'cost' => $rate['cost'],
                    'free_shipping_threshold' => null,
                ]);
            }
        }
    }
}
