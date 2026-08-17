<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Two purpose-named checkout messages: the existing `comment` is the
// message to the store (pack carefully); `courier_comment` is the message
// to the courier (deliver on these dates). Core owns both columns; the
// fulfillment module reads courier_comment when booking, so the coupling
// points from the module to core, never the other way.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('courier_comment')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('courier_comment');
        });
    }
};
