<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which extension a scheduled job belongs to, so the job leaves with it:
 * it is not scheduled while the extension is disabled, and it is deleted
 * when the extension is uninstalled. Null for core's own jobs. The owning
 * seeder claims its row on next run. Additive, reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            $table->string('extension', 64)->nullable()->after('key')->index();
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table) {
            // The index first: SQLite refuses to drop an indexed column.
            $table->dropIndex(['extension']);
            $table->dropColumn('extension');
        });
    }
};
