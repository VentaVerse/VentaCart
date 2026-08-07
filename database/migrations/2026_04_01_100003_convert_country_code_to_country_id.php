<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Build iso2 → id mapping
        $countryMap = DB::table('countries')->pluck('id', 'iso2')->toArray();

        // --- 1. ZONES: country_code (varchar) → country_id (bigint FK) ---
        if (!Schema::hasColumn('zones', 'country_id')) {
            Schema::table('zones', function (Blueprint $table) {
                $table->unsignedBigInteger('country_id')->nullable()->after('id');
            });
        }

        // Migrate data
        foreach ($countryMap as $iso2 => $countryId) {
            DB::table('zones')->where('country_code', $iso2)->update(['country_id' => $countryId]);
        }

        // Drop old FK and column
        if (Schema::hasColumn('zones', 'country_code')) {
            try {
                Schema::table('zones', function (Blueprint $table) {
                    $table->dropForeign(['country_code']);
                });
            } catch (\Throwable) {
                $prefix = DB::getTablePrefix();
                try {
                    DB::statement("ALTER TABLE `{$prefix}zones` DROP FOREIGN KEY `zones_country_code_foreign`");
                } catch (\Throwable) {}
            }
            // Drop the index on country_code first — SQLite refuses to drop
            // columns that are still referenced by an index.
            try {
                Schema::table('zones', function (Blueprint $table) {
                    $table->dropIndex(['country_code']);
                });
            } catch (\Throwable) {
                // Index may already be dropped or have a different name
            }
            Schema::table('zones', function (Blueprint $table) {
                $table->dropColumn('country_code');
            });
        }

        // Add proper FK
        Schema::table('zones', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->index('country_id');
        });

        // --- 2. CUSTOMER_ADDRESSES: country_code → country_id ---
        if (!Schema::hasColumn('customer_addresses', 'country_id')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->unsignedBigInteger('country_id')->nullable()->after('country_code');
            });
        }

        foreach ($countryMap as $iso2 => $countryId) {
            DB::table('customer_addresses')->where('country_code', $iso2)->update(['country_id' => $countryId]);
        }

        if (Schema::hasColumn('customer_addresses', 'country_code')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->dropColumn('country_code');
            });
        }

        // --- 3. ORDERS: shipping_country_code → shipping_country_id, billing_country_code → billing_country_id ---
        if (!Schema::hasColumn('orders', 'shipping_country_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('shipping_country_id')->nullable()->after('shipping_country_code');
                $table->unsignedBigInteger('billing_country_id')->nullable()->after('billing_country_code');
            });
        }

        foreach ($countryMap as $iso2 => $countryId) {
            DB::table('orders')->where('shipping_country_code', $iso2)->update(['shipping_country_id' => $countryId]);
            DB::table('orders')->where('billing_country_code', $iso2)->update(['billing_country_id' => $countryId]);
        }

        $orderColsToDrop = array_filter(['shipping_country_code', 'billing_country_code'], fn($c) => Schema::hasColumn('orders', $c));
        if (!empty($orderColsToDrop)) {
            Schema::table('orders', function (Blueprint $table) use ($orderColsToDrop) {
                $table->dropColumn($orderColsToDrop);
            });
        }

        // --- 4. Drop unique constraint on countries.iso2 ---
        try {
            Schema::table('countries', function (Blueprint $table) {
                $table->dropUnique('countries_iso2_unique');
            });
        } catch (\Throwable) {
            // Index name may differ with table prefix
            $prefix = DB::getTablePrefix();
            try {
                DB::statement("ALTER TABLE `{$prefix}countries` DROP INDEX `{$prefix}countries_iso2_unique`");
            } catch (\Throwable) {
                // Already dropped or doesn't exist
            }
        }
        Schema::table('countries', function (Blueprint $table) {
            $table->index('iso2');
        });
    }

    public function down(): void
    {
        $countryMap = DB::table('countries')->pluck('iso2', 'id')->toArray();

        // Restore countries.iso2 unique
        Schema::table('countries', function (Blueprint $table) {
            $table->dropIndex(['iso2']);
            $table->unique('iso2');
        });

        // Restore orders
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_country_code', 3)->nullable()->after('shipping_postcode');
            $table->string('billing_country_code', 3)->nullable()->after('billing_postcode');
        });
        foreach ($countryMap as $id => $iso2) {
            DB::table('orders')->where('shipping_country_id', $id)->update(['shipping_country_code' => $iso2]);
            DB::table('orders')->where('billing_country_id', $id)->update(['billing_country_code' => $iso2]);
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_country_id', 'billing_country_id']);
        });

        // Restore customer_addresses
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('country_code', 3)->nullable()->after('postcode');
        });
        foreach ($countryMap as $id => $iso2) {
            DB::table('customer_addresses')->where('country_id', $id)->update(['country_code' => $iso2]);
        }
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn('country_id');
        });

        // Restore zones
        Schema::table('zones', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('id');
        });
        foreach ($countryMap as $id => $iso2) {
            DB::table('zones')->where('country_id', $id)->update(['country_code' => $iso2]);
        }
        Schema::table('zones', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropIndex(['country_id']);
            $table->dropColumn('country_id');
            $table->foreign('country_code')->references('iso2')->on('countries')->cascadeOnDelete();
        });
    }
};
