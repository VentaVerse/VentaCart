<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_histories', function (Blueprint $table) {
            // What the customer was told about this change, when it differs
            // from the row's own comment (an ERP fallback line or a
            // fulfilment audit suffix belongs on the record, not in front of
            // the shopper). Null means "the same as comment", which is every
            // row written before this column existed.
            $table->text('customer_comment')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('order_histories', function (Blueprint $table) {
            $table->dropColumn('customer_comment');
        });
    }
};
