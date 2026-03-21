<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('length', 8, 2)->default(0)->after('weight_unit');
            $table->decimal('width', 8, 2)->default(0)->after('length');
            $table->decimal('height', 8, 2)->default(0)->after('width');
            $table->date('date_available')->nullable()->after('is_active');
        });

        Schema::create('customer_group_product', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_group_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_group_product');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['length', 'width', 'height', 'date_available']);
        });
    }
};
