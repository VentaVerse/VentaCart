<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->cascadeOnDelete();
            $table->boolean('is_auto')->default(false);
            $table->timestamps();

            $table->unique(['store_id', 'slug', 'parent_id']);
        });

        Schema::create('store_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_folder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->string('disk', 20)->default('public');
            $table->string('original_filename');
            $table->string('mime_type', 50);
            $table->unsignedInteger('file_size')->default(0);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->boolean('is_temp')->default(false);
            $table->timestamps();

            $table->index(['store_id', 'media_folder_id']);
        });

        // Update product_images to reference store_media
        Schema::table('product_images', function (Blueprint $table) {
            $table->foreignId('store_media_id')->nullable()->after('product_id')->constrained('store_media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_media_id');
        });
        Schema::dropIfExists('store_media');
        Schema::dropIfExists('media_folders');
    }
};
