<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('free_shipping_rules')) {
            return;
        }

        if (!Schema::hasColumn('free_shipping_rules', 'checkout_name')) {
            Schema::table('free_shipping_rules', function (Blueprint $table) {
                $table->string('checkout_name')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('free_shipping_rules', 'checkout_name')) {
            Schema::table('free_shipping_rules', function (Blueprint $table) {
                $table->dropColumn('checkout_name');
            });
        }
    }
};
