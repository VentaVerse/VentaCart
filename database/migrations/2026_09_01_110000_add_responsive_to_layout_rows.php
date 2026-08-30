<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A row's columns on smaller screens: widths on tablets and the order
 * they stack in on phones. Null is the default (tablets keep the desktop
 * widths, phones stack in order).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layout_rows', function (Blueprint $table) {
            $table->json('responsive')->nullable()->after('style');
        });
    }

    public function down(): void
    {
        Schema::table('layout_rows', function (Blueprint $table) {
            $table->dropColumn('responsive');
        });
    }
};
