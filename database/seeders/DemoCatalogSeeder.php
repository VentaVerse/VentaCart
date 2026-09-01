<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The shop a fresh install opens on.
 *
 * A store with nothing in it cannot show what it does: no category page, no
 * brand strip, no variant picker, no product card. So an install lays down a
 * small electronics catalogue with its own photography, the way OpenCart has
 * always shipped one, and the merchant deletes it from the admin when they are
 * ready to put their own goods in.
 *
 * The names are invented. Nothing here borrows a real maker's name or mark,
 * and the photographs carry no logo or lettering, so the demo can be shipped
 * and sold without standing on anybody's trademark.
 *
 * It runs once, into an empty catalogue. A store that already holds a single
 * product of the merchant's own is left alone for good.
 */
class DemoCatalogSeeder extends Seeder
{
    /**
     * Where the tracked photography is published to, under public storage.
     *
     * Into the media library's own roots, not a folder of our own. The library
     * is a filesystem browser over an allow list (products, brands, categories,
     * blog, banners), so anything written outside those roots is invisible in
     * the picker and unpickable by the merchant: the images would show on the
     * storefront but could not be replaced or deleted from the admin.
     *
     * A demo/ subfolder inside each root keeps them together and makes them
     * one thing to delete when the merchant is ready to clear the demo out.
     * Every subfolder of a reachable root is itself reachable, and the root
     * card counts files and folders separately, so a folder holding nothing
     * but this one says so rather than promising pictures it does not have.
     */
    private const PUBLISH = [
        'products' => 'media/products/demo',
        'categories' => 'media/categories/demo',
        'brands' => 'media/brands/demo',
        // The home page's hero photograph, which the layout seeder points a
        // row background at.
        'hero' => 'media/hero',
    ];

    public function run(): void
    {
        $empty = ! Product::withoutGlobalScopes()->exists() && ! Category::query()->exists();

        // Publish the photographs whenever this store is one the demo belongs
        // to: either it is about to be seeded, or it was seeded before and its
        // storage has since been emptied. Public storage is not in git, so a
        // fresh checkout of an existing store arrives with the records and none
        // of the pictures, and seeding is what puts them back.
        $hasDemo = Product::withoutGlobalScopes()
            ->where('image', 'like', self::PUBLISH['products'] . '/%')
            ->exists();

        if ($empty || $hasDemo) {
            $this->publishImages();
        }

        if (! $empty) {
            $this->command?->info('Catalogue is not empty, demo goods skipped.');

            return;
        }

        DB::transaction(function () {
            $brands = $this->brands();
            $categories = $this->categories();
            $this->products($brands, $categories);
        });

        $this->command?->info(sprintf(
            'Demo catalogue: %d products, %d categories, %d brands.',
            Product::withoutGlobalScopes()->count(),
            Category::query()->count(),
            Brand::query()->count(),
        ));
    }

    /**
     * Copy the theme-independent demo photography into public storage. Never
     * stands on a file the merchant put there, so re-running is harmless.
     */
    private function publishImages(): void
    {
        foreach (self::PUBLISH as $group => $target) {
            $from = resource_path("demo/store/{$group}");
            $to = storage_path('app/public/' . $target);

            if (! is_dir($from)) {
                continue;
            }

            is_dir($to) || @mkdir($to, 0775, true);

            foreach (glob($from . '/*.jpg') ?: [] as $file) {
                $dest = $to . '/' . basename($file);

                // Never stand on a file the merchant put there.
                if (! file_exists($dest)) {
                    @copy($file, $dest);
                }
            }
        }
    }

    private function img(string $group, string $file): string
    {
        return self::PUBLISH[$group] . "/{$file}";
    }

    /** @return array<string, Brand> */
    private function brands(): array
    {
        $out = [];

        foreach ([
            ['Aurora', 'Phones built to last a contract and then some. Aurora has made one flagship a year since 2011 and supports each for six.'],
            ['Northwind', 'Portable computers for people who actually carry them. Thin, quiet, and repairable, which is rarer than it should be.'],
            ['Lumen', 'Colour accurate displays for studios and desks. Every panel leaves the factory individually calibrated with its report in the box.'],
            ['Kestrel', 'Tablets that are pleasant to draw on and simple to hand to somebody else. Nothing here needs a manual.'],
            ['Vireo', 'The small things that make the big things work: hubs, cables, earbuds, keyboards.'],
        ] as $i => [$name, $description]) {
            $out[$name] = Brand::create([
                'store_id' => 1,
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'description' => $description,
                'image' => $this->img('brands', strtolower($name) . '.jpg'),
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        return $out;
    }

    /**
     * Four top level departments, with Computers holding two children so a
     * fresh install shows a nested menu rather than a flat list.
     *
     * @return array<string, Category>
     */
    private function categories(): array
    {
        $out = [];
        $sort = 0;

        $make = function (string $name, ?string $image, ?Category $parent, string $description) use (&$out, &$sort): Category {
            return $out[$name] = Category::create([
                'store_id' => 1,
                'parent_id' => $parent?->id,
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'description' => $description,
                'image' => $image ? $this->img('categories', $image) : null,
                'sort_order' => $sort++,
                'is_active' => true,
            ]);
        };

        $make('Phones', 'phones.jpg', null, 'Flagship and everyday handsets, unlocked and ready for any network.');
        $computers = $make('Computers', 'computers.jpg', null, 'Laptops and the displays to put beside them.');
        $make('Laptops', 'laptops.jpg', $computers, 'Thin, quiet machines for work that moves around.');
        $make('Monitors', 'monitors.jpg', $computers, 'Colour accurate panels from a tidy 27 inch to a very wide 34.');
        $make('Tablets', 'tablets.jpg', null, 'For drawing, reading and handing to somebody across a table.');
        $make('Accessories', 'accessories.jpg', null, 'Hubs, earbuds, keyboards and the cables that connect them.');

        return $out;
    }

    /**
     * @param  array<string, Brand>  $brands
     * @param  array<string, Category>  $categories
     */
    private function products(array $brands, array $categories): void
    {
        $rows = [
            [
                'name' => 'Aurora 15 Pro', 'brand' => 'Aurora', 'cats' => ['Phones'],
                'price' => 1199, 'quantity' => 40, 'weight' => 0.221,
                'image' => 'phone-pro-graphite.jpg',
                'gallery' => ['phone-pro-blue.jpg', 'phone-pro-desert.jpg'],
                'views' => 1840,
                'description' => "A 6.7 inch display that stays readable in direct sun, a camera that does not need good light, and a battery that finishes the day with something left.\n\nBuilt from a single piece of titanium, so it is lighter than last year while being harder to bend. Six years of operating system updates from the day it ships.",
            ],
            [
                'name' => 'Aurora 15', 'brand' => 'Aurora', 'cats' => ['Phones'],
                'price' => 899, 'sale' => 799, 'quantity' => 65, 'weight' => 0.171,
                'image' => 'phone-compact-pink.jpg', 'views' => 1320,
                'description' => "The same processor and the same main camera as the Pro, in a 6.1 inch body that still fits a pocket properly.\n\nFor most people this is the phone. The Pro adds a telephoto lens and an hour of battery, and costs three hundred more to do it.",
            ],
            [
                'name' => 'Northwind Air 13', 'brand' => 'Northwind', 'cats' => ['Laptops', 'Computers'],
                'price' => 949, 'quantity' => 30, 'weight' => 1.24,
                'image' => 'laptop-air-13.jpg', 'views' => 1560,
                'description' => "Fanless, so it makes no noise at all, and thin enough that you stop noticing it in a bag. Eighteen hours of real use between charges.\n\nThe keyboard is the good one, with proper travel, and the lid opens with one finger.",
            ],
            [
                'name' => 'Northwind Studio 14', 'brand' => 'Northwind', 'cats' => ['Laptops', 'Computers'],
                'price' => 1599, 'sale' => 1439, 'quantity' => 18, 'weight' => 1.55,
                'image' => 'laptop-pro-14.jpg', 'gallery' => ['laptop-closed-stack.jpg'], 'views' => 2100,
                'description' => "For work that renders, compiles or exports. Sustained performance rather than a fast burst that throttles ten minutes in.\n\nThree USB-C ports, a card reader and a headphone socket, because dongles are somebody else's idea of progress.",
            ],
            [
                'name' => 'Lumen 27 inch 4K Display', 'brand' => 'Lumen', 'cats' => ['Monitors', 'Computers'],
                'price' => 449, 'quantity' => 44, 'weight' => 6.1,
                'image' => 'monitor-27-4k.jpg', 'views' => 980,
                'description' => "A 27 inch 4K panel calibrated at the factory, with its measurement report in the box. 99 percent sRGB and 95 percent P3.\n\nOne USB-C cable carries picture, data and 90W of power, so a laptop needs nothing else on the desk.",
            ],
            [
                'name' => 'Lumen 34 inch Ultrawide', 'brand' => 'Lumen', 'cats' => ['Monitors', 'Computers'],
                'price' => 699, 'quantity' => 22, 'weight' => 8.4,
                'image' => 'monitor-34-ultrawide.jpg', 'views' => 1240,
                'description' => "The width of two monitors without the bezel down the middle. Gently curved, so the far edges are the same distance from you as the centre.\n\nSplits into two independent inputs side by side when you need a second machine on screen.",
            ],
            [
                'name' => 'Lumen Go 15 inch Portable', 'brand' => 'Lumen', 'cats' => ['Monitors', 'Computers'],
                'price' => 229, 'sale' => 189, 'quantity' => 50, 'weight' => 0.79,
                'image' => 'monitor-portable.jpg', 'views' => 640,
                'description' => "A second screen that lives in a laptop sleeve. One cable, no power brick, and a cover that folds into a stand.\n\nBright enough for a hotel desk, and it turns to portrait for reading long documents.",
            ],
            [
                'name' => 'Kestrel Tab 11 Pro', 'brand' => 'Kestrel', 'cats' => ['Tablets'],
                'price' => 799, 'quantity' => 26, 'weight' => 0.466,
                'image' => 'tablet-pro-11.jpg', 'gallery' => ['tablet-keyboard-case.jpg'], 'views' => 1450,
                'description' => "A 120Hz screen that makes a stylus feel like it is leaving ink rather than reporting a position. Laminated, so the nib meets the line.\n\nTakes the keyboard case and becomes a small laptop when you need to answer email properly.",
            ],
            [
                'name' => 'Kestrel Tab Air', 'brand' => 'Kestrel', 'cats' => ['Tablets'],
                'price' => 499, 'quantity' => 38, 'weight' => 0.462,
                'image' => 'tablet-stylus.jpg', 'views' => 870,
                'description' => "The tablet most households actually want: light, fast enough for everything except professional video, and happy to be handed to a child.\n\nSupports the same stylus as the Pro.",
            ],
            [
                'name' => 'Vireo Buds Pro', 'brand' => 'Vireo', 'cats' => ['Accessories'],
                'price' => 159, 'sale' => 129, 'quantity' => 120, 'weight' => 0.061,
                'image' => 'earbuds-case.jpg', 'views' => 2260,
                'description' => "Noise cancelling you can turn down rather than only off, and a transparency mode that does not sound like a microphone.\n\nSix hours in the buds, twenty four more in the case, and a five minute charge gets an hour back.",
            ],
            [
                'name' => 'Vireo USB-C Hub', 'brand' => 'Vireo', 'cats' => ['Accessories'],
                'price' => 69, 'quantity' => 200, 'weight' => 0.098,
                'image' => 'hub-usbc.jpg', 'views' => 1130,
                'description' => "HDMI, two USB-A, a card reader and 100W pass through charging, in a machined aluminium body that does not get hot.\n\nThe cable is short on purpose, so the hub sits next to the laptop rather than hanging off it.",
            ],
            [
                'name' => 'Vireo Keys and Mouse', 'brand' => 'Vireo', 'cats' => ['Accessories'],
                'price' => 99, 'quantity' => 85, 'weight' => 0.62,
                'image' => 'keyboard-mouse-set.jpg', 'views' => 720,
                'description' => "A low profile keyboard and a slim mouse that pair to three machines at once and switch between them with one key.\n\nRechargeable, and a full charge lasts about two months.",
            ],
        ];

        foreach ($rows as $i => $row) {
            $product = Product::withoutGlobalScopes()->create([
                'store_id' => 1,
                'name' => $row['name'],
                'slug' => \Illuminate\Support\Str::slug($row['name']),
                'sku' => 'DEMO-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'brand_id' => $brands[$row['brand']]->id,
                'description' => $row['description'],
                'price' => $row['price'],
                'quantity' => $row['quantity'],
                'weight' => $row['weight'],
                'weight_unit' => 'kg',
                'image' => $this->img('products', $row['image']),
                'is_active' => true,
                'track_stock' => true,
                // Newest first in the order they are listed, so "just landed"
                // and "most viewed" are not the same twelve cards twice.
                'date_available' => now()->subDays($i),
                'views' => $row['views'],
                'sort_order' => $i,
            ]);

            $product->categories()->sync(array_map(fn ($c) => $categories[$c]->id, $row['cats']));

            foreach (array_values($row['gallery'] ?? []) as $n => $file) {
                DB::table('product_images')->insert([
                    'product_id' => $product->id,
                    'path' => $this->img('products', $file),
                    'sort_order' => $n,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (! empty($row['sale'])) {
                DB::table('product_specials')->insert([
                    'product_id' => $product->id,
                    'customer_group_id' => null,
                    'price' => $row['sale'],
                    'quantity' => 1,
                    'display_mode' => 'sale',
                    'priority' => 1,
                    'date_start' => null,
                    'date_end' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($row['name'] === 'Aurora 15 Pro') {
                $this->variants($product);
            }
        }
    }

    /**
     * The one product that shows the variant picker doing real work: three
     * colours against three capacities, nine combinations, each with its own
     * stock and its own photograph for the colour it is.
     */
    private function variants(Product $product): void
    {
        $colours = [
            'Graphite' => 'phone-pro-graphite.jpg',
            'Blue' => 'phone-pro-blue.jpg',
            'Desert' => 'phone-pro-desert.jpg',
        ];
        // Capacity is what costs money; the colour is free.
        $storage = ['256GB' => 0, '512GB' => 100, '1TB' => 300];

        $colourType = DB::table('variant_types')->insertGetId([
            'product_id' => $product->id, 'name' => 'Colour', 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $storageType = DB::table('variant_types')->insertGetId([
            'product_id' => $product->id, 'name' => 'Storage', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $colourValue = [];
        foreach (array_keys($colours) as $n => $name) {
            $colourValue[$name] = DB::table('variant_type_values')->insertGetId([
                'variant_type_id' => $colourType, 'value' => $name, 'sort_order' => $n,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $storageValue = [];
        foreach (array_keys($storage) as $n => $name) {
            $storageValue[$name] = DB::table('variant_type_values')->insertGetId([
                'variant_type_id' => $storageType, 'value' => $name, 'sort_order' => $n,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $n = 0;

        foreach ($colours as $colour => $file) {
            foreach ($storage as $size => $modifier) {
                $variantId = DB::table('product_variants')->insertGetId([
                    'product_id' => $product->id,
                    'sku' => sprintf('DEMO-001-%s-%s', strtoupper(substr($colour, 0, 2)), rtrim($size, 'GBTB')),
                    // Both columns are stored: price is what the variant costs,
                    // price_modifier is its distance from the product's base.
                    // The admin form writes the pair the same way.
                    'price' => (float) $product->price + $modifier,
                    'price_modifier' => $modifier,
                    // Deliberately uneven, so the demo shows a variant picker
                    // with something actually out of stock in it.
                    'quantity' => $size === '1TB' && $colour === 'Desert' ? 0 : 12 - $n,
                    'image' => $this->img('products', $file),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('product_variant_values')->insert([
                    ['product_variant_id' => $variantId, 'variant_type_value_id' => $colourValue[$colour]],
                    ['product_variant_id' => $variantId, 'variant_type_value_id' => $storageValue[$size]],
                ]);

                $n++;
            }
        }

        $product->update(['has_variants' => true]);
    }
}
