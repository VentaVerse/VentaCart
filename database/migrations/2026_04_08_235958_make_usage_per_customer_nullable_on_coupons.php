<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('coupons')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('usage_per_customer')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('coupons')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('usage_per_customer')->nullable(false)->default(1)->change();
        });
    }
};
