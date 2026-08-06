<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shipping_zone_rates');
    }

    public function down(): void
    {
        // Intentionally irreversible: the table was orphaned (no quote provider
        // ever read it). Restore from backup if ever needed.
    }
};
