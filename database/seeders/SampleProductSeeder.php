<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SampleProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ngd = \App\Models\Store::where('code', 'ngd')->first();
        $ds = \App\Models\Store::where('code', 'ds')->first();

        // --- Customer Groups ---
        \App\Models\CustomerGroup::create(['store_id' => $ngd->id, 'name' => 'Direct Customer', 'is_default' => true]);
        \App\Models\CustomerGroup::create(['store_id' => $ngd->id, 'name' => 'Distributor']);
        \App\Models\CustomerGroup::create(['store_id' => $ngd->id, 'name' => 'Wholesaler']);
        \App\Models\CustomerGroup::create(['store_id' => $ds->id, 'name' => 'Direct Customer', 'is_default' => true]);
        \App\Models\CustomerGroup::create(['store_id' => $ds->id, 'name' => 'Distributor']);

        // --- Categories for NGD ---
        $effects = \App\Models\Category::create([
            'store_id' => $ngd->id, 'name' => 'Guitar Effects', 'slug' => 'guitar-effects',
            'description' => 'Guitar effect pedals and processors',
            'is_active' => true, 'meta_title' => 'Guitar Effects Pedals | Venta',
        ]);
        $cables = \App\Models\Category::create([
            'store_id' => $ngd->id, 'name' => 'Cables', 'slug' => 'cables',
            'description' => 'Instrument and patch cables',
            'is_active' => true, 'meta_title' => 'Guitar Cables | Venta',
        ]);
        $parts = \App\Models\Category::create([
            'store_id' => $ngd->id, 'name' => 'Parts & Accessories', 'slug' => 'parts-accessories',
            'is_active' => true, 'meta_title' => 'Guitar Parts & Accessories | Venta',
        ]);

        // Sub-categories
        \App\Models\Category::create([
            'store_id' => $ngd->id, 'parent_id' => $effects->id,
            'name' => 'Overdrive', 'slug' => 'overdrive', 'is_active' => true,
        ]);
        \App\Models\Category::create([
            'store_id' => $ngd->id, 'parent_id' => $effects->id,
            'name' => 'Delay', 'slug' => 'delay', 'is_active' => true,
        ]);
        $patchCables = \App\Models\Category::create([
            'store_id' => $ngd->id, 'parent_id' => $cables->id,
            'name' => 'Patch Cables', 'slug' => 'patch-cables', 'is_active' => true,
        ]);

        // --- Categories for Dragon Switch ---
        \App\Models\Category::create([
            'store_id' => $ds->id, 'name' => 'Footswitches', 'slug' => 'footswitches', 'is_active' => true,
        ]);
        \App\Models\Category::create([
            'store_id' => $ds->id, 'name' => 'Connectors', 'slug' => 'connectors', 'is_active' => true,
        ]);

        // --- Product 1: Simple product (no variants) ---
        $pedal = \App\Models\Product::create([
            'store_id' => $ngd->id,
            'name' => 'Boss DS-1 Distortion Pedal',
            'slug' => 'boss-ds-1-distortion-pedal',
            'sku' => 'NGD-BOSS-DS1',
            'model' => 'DS-1',
            'description' => 'The classic Boss DS-1 distortion pedal. Hard-edged distortion sound.',
            'price' => 3500.00,
            'cost' => 2200.00,
            'quantity' => 15,
            'weight' => 0.400,
            'has_variants' => false,
            'is_active' => true,
            'meta_title' => 'Boss DS-1 Distortion Pedal | Venta',
            'meta_description' => 'Buy the Boss DS-1 Distortion Pedal. Classic hard-edged distortion for guitar.',
        ]);
        $pedal->categories()->attach([$effects->id]);

        // --- Product 2: With variants (cable with length + color) ---
        $cable = \App\Models\Product::create([
            'store_id' => $ngd->id,
            'name' => 'Premium Patch Cable',
            'slug' => 'premium-patch-cable',
            'sku' => 'NGD-PATCH',
            'description' => 'Low-noise premium patch cable for pedalboards. Available in multiple lengths and colors.',
            'price' => 350.00,
            'cost' => 150.00,
            'quantity' => 0, // stock tracked at variant level
            'weight' => 0.050,
            'has_variants' => true,
            'is_active' => true,
            'meta_title' => 'Premium Patch Cable | Venta',
        ]);
        $cable->categories()->attach([$cables->id, $patchCables->id]);

        // Variant Type: Length
        $lengthType = \App\Models\VariantType::create([
            'product_id' => $cable->id, 'name' => 'Length', 'sort_order' => 1,
        ]);
        $length15 = \App\Models\VariantTypeValue::create(['variant_type_id' => $lengthType->id, 'value' => '15cm', 'sort_order' => 1]);
        $length30 = \App\Models\VariantTypeValue::create(['variant_type_id' => $lengthType->id, 'value' => '30cm', 'sort_order' => 2]);
        $length60 = \App\Models\VariantTypeValue::create(['variant_type_id' => $lengthType->id, 'value' => '60cm', 'sort_order' => 3]);

        // Variant Type: Color
        $colorType = \App\Models\VariantType::create([
            'product_id' => $cable->id, 'name' => 'Color', 'sort_order' => 2,
        ]);
        $black = \App\Models\VariantTypeValue::create(['variant_type_id' => $colorType->id, 'value' => 'Black', 'sort_order' => 1]);
        $red = \App\Models\VariantTypeValue::create(['variant_type_id' => $colorType->id, 'value' => 'Red', 'sort_order' => 2]);

        // Variant combinations: 3 lengths x 2 colors = 6 variants, each with own SKU/price/cost
        $combos = [
            ['values' => [$length15, $black], 'sku' => 'NGD-PATCH-15-BLK', 'price' => 300, 'cost' => 120, 'qty' => 25],
            ['values' => [$length15, $red],   'sku' => 'NGD-PATCH-15-RED', 'price' => 320, 'cost' => 130, 'qty' => 20],
            ['values' => [$length30, $black], 'sku' => 'NGD-PATCH-30-BLK', 'price' => 400, 'cost' => 170, 'qty' => 18],
            ['values' => [$length30, $red],   'sku' => 'NGD-PATCH-30-RED', 'price' => 420, 'cost' => 180, 'qty' => 12],
            ['values' => [$length60, $black], 'sku' => 'NGD-PATCH-60-BLK', 'price' => 550, 'cost' => 250, 'qty' => 10],
            ['values' => [$length60, $red],   'sku' => 'NGD-PATCH-60-RED', 'price' => 570, 'cost' => 260, 'qty' => 8],
        ];

        foreach ($combos as $combo) {
            $variant = \App\Models\ProductVariant::create([
                'product_id' => $cable->id,
                'sku' => $combo['sku'],
                'price' => $combo['price'],
                'cost' => $combo['cost'],
                'quantity' => $combo['qty'],
            ]);
            $variant->values()->attach(collect($combo['values'])->pluck('id'));
        }

        // --- Product 3: Dragon Switch product (single variant type) ---
        $footswitch = \App\Models\Product::create([
            'store_id' => $ds->id,
            'name' => 'Momentary Soft-Touch Footswitch',
            'slug' => 'momentary-soft-touch-footswitch',
            'sku' => 'DS-MOM-SW',
            'description' => 'Premium momentary soft-touch footswitch for guitar pedal builds.',
            'price' => 180.00,
            'cost' => 65.00,
            'quantity' => 0,
            'weight' => 0.030,
            'has_variants' => true,
            'is_active' => true,
        ]);

        $colorType2 = \App\Models\VariantType::create([
            'product_id' => $footswitch->id, 'name' => 'Color', 'sort_order' => 1,
        ]);
        $colors = [
            ['value' => 'Silver', 'sku' => 'DS-MOM-SW-SLV', 'price' => 180, 'cost' => 65, 'qty' => 50],
            ['value' => 'Black',  'sku' => 'DS-MOM-SW-BLK', 'price' => 180, 'cost' => 65, 'qty' => 45],
            ['value' => 'Blue',   'sku' => 'DS-MOM-SW-BLU', 'price' => 200, 'cost' => 70, 'qty' => 30],
            ['value' => 'Red',    'sku' => 'DS-MOM-SW-RED', 'price' => 200, 'cost' => 70, 'qty' => 30],
        ];

        foreach ($colors as $c) {
            $val = \App\Models\VariantTypeValue::create([
                'variant_type_id' => $colorType2->id, 'value' => $c['value'], 'sort_order' => 0,
            ]);
            $variant = \App\Models\ProductVariant::create([
                'product_id' => $footswitch->id,
                'sku' => $c['sku'],
                'price' => $c['price'],
                'cost' => $c['cost'],
                'quantity' => $c['qty'],
            ]);
            $variant->values()->attach([$val->id]);
        }

        // --- Sample Customer ---
        $defaultGroup = \App\Models\CustomerGroup::where('store_id', $ngd->id)->where('is_default', true)->first();
        \App\Models\Customer::create([
            'store_id' => $ngd->id,
            'customer_group_id' => $defaultGroup->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '+639171234567',
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
