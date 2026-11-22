<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * product_specials as a store had it before specials and discounts were
 * split, for tests of shipped migrations that ran against that shape. A
 * migrated test database no longer has it, and the split cannot be rolled
 * back, so the old table is rebuilt directly.
 */
trait LegacySpecialsTable
{
    protected const SPLIT_MIGRATION = '2026_11_23_100000_split_product_specials_into_specials_and_discounts';

    protected function legacySpecialsTable(): void
    {
        Schema::dropIfExists('product_discounts');
        Schema::dropIfExists('product_specials');

        Schema::create('product_specials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price', 12, 2);
            $table->integer('quantity')->default(1);
            $table->enum('display_mode', ['override', 'sale'])->default('sale');
            $table->integer('priority')->default(0);
            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_end')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'priority']);
            $table->index(['product_id', 'display_mode', 'date_start', 'date_end'], 'ps_product_display_dates_index');
        });

        DB::table('migrations')->where('migration', self::SPLIT_MIGRATION)->delete();
    }
}
