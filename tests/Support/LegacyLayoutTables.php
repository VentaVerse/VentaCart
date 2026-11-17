<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Layout tables as a store had them before they were removed, for
 * tests of the shipped migrations that changed their rows. A migrated test
 * database no longer has them, because a later migration drops them.
 */
trait LegacyLayoutTables
{
    protected function legacyLayoutTables(): void
    {
        if (! Schema::hasTable('layout_rows')) {
            Schema::create('layout_rows', function (Blueprint $table) {
                $table->id();
                $table->string('theme', 64)->default('default');
                $table->string('page_type', 64);
                $table->string('region', 64);
                $table->json('columns')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('layout_placements')) {
            Schema::create('layout_placements', function (Blueprint $table) {
                $table->id();
                $table->string('theme', 64)->default('default');
                $table->string('page_type', 64);
                $table->string('region', 64);
                $table->unsignedBigInteger('row_id')->nullable();
                $table->string('widget', 64);
                $table->string('title')->nullable();
                $table->json('config')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('layout_templates')) {
            Schema::create('layout_templates', function (Blueprint $table) {
                $table->id();
                $table->string('theme', 64)->default('default');
                $table->string('name');
                $table->string('region', 64)->nullable();
                $table->json('rows')->nullable();
                $table->timestamps();
            });
        }
    }
}
