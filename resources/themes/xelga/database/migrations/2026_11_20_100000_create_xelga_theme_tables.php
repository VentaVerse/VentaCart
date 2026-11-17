<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Everything the theme editor saves: documents (theme settings, header and
 * footer groups, page templates), their published revisions, and custom
 * template assignments. Uninstalling the theme runs down(), which drops
 * all three.
 *
 * Installing also copies the starting store's photos into the media
 * library, so the shipped defaults show their pictures from the first
 * page view, and the Clothes store's, which the section presets show. A
 * file already there is never replaced, and uninstalling leaves the
 * photos, which are the merchant's media from then on.
 *
 * A store that already shows an announcement line keeps it: the line
 * becomes the only message of the theme's announcement bar, published as
 * the store's header. Core's setting is only read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xelga_documents', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 64)->unique('xelga_documents_handle_unique');
            $table->string('kind', 16);
            $table->string('page_type', 32)->nullable();
            $table->string('label', 80)->nullable();
            $table->json('published')->nullable();
            $table->unsignedInteger('published_version')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->json('draft')->nullable();
            $table->unsignedInteger('draft_version')->default(0);
            $table->unsignedInteger('draft_base_version')->nullable();
            $table->unsignedBigInteger('draft_updated_by')->nullable();
            $table->timestamp('draft_updated_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'page_type'], 'xelga_documents_kind_page_index');
        });

        Schema::create('xelga_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('xelga_documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('content');
            $table->string('source', 16);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['document_id', 'version'], 'xelga_revisions_document_version_index');
        });

        Schema::create('xelga_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('document_id')->constrained('xelga_documents')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id'], 'xelga_assignments_subject_unique');
        });

        $this->copyStartingPhotos();
        $this->carryTheAnnouncement();
    }

    public function down(): void
    {
        Schema::dropIfExists('xelga_assignments');
        Schema::dropIfExists('xelga_revisions');
        Schema::dropIfExists('xelga_documents');
    }

    private function copyStartingPhotos(): void
    {
        try {
            $disk = Storage::disk('public');
            foreach (['gadgets', 'clothes'] as $store) {
                $from = dirname(__DIR__, 2) . '/store-templates/' . $store . '/images';
                $files = is_dir($from) ? glob($from . '/*') ?: [] : [];
                foreach (array_filter($files, fn ($f) => is_file($f) && preg_match('/\.(jpe?g|png|webp)$/i', $f) === 1) as $file) {
                    $target = 'media/xelga/' . $store . '/' . basename($file);
                    if (! $disk->exists($target)) {
                        $disk->put($target, (string) file_get_contents($file));
                    }
                }
            }
        } catch (\Throwable $e) {
            // The pages still render without their photos; the merchant
            // picks new ones in the editor.
            Log::warning('The starting store photos could not be copied into the media library.', ['error' => $e->getMessage()]);
        }
    }

    private function carryTheAnnouncement(): void
    {
        try {
            $line = DB::table('settings')->where('group', 'header')
                ->whereIn('key', ['announcement_enabled', 'announcement_text', 'announcement_link'])
                ->pluck('value', 'key');
            $text = trim((string) ($line['announcement_text'] ?? ''));
            if (($line['announcement_enabled'] ?? '0') !== '1' || $text === '') {
                return;
            }

            $header = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/store-templates/_defaults/header.json'), true);
            $block = 'announcement-1-b1';
            if (! is_array($header) || ! isset($header['sections']['announcement-1']['blocks'][$block])) {
                return;
            }

            $href = \App\Support\SafeInput::link((string) ($line['announcement_link'] ?? ''));
            $header['sections']['announcement-1']['block_order'] = [$block];
            $header['sections']['announcement-1']['blocks'] = [$block => array_replace_recursive(
                $header['sections']['announcement-1']['blocks'][$block],
                ['settings' => ['text' => mb_substr($text, 0, 120), 'link' => $href === '' ? null : ['type' => 'url', 'href' => $href]]]
            )];
            $header['sections']['announcement-1']['disabled'] = false;

            $now = now();
            $json = json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $id = DB::table('xelga_documents')->insertGetId([
                'handle' => 'header',
                'kind' => 'group',
                'page_type' => null,
                'published' => $json,
                'published_version' => 1,
                'published_at' => $now,
                'draft_version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('xelga_revisions')->insert([
                'document_id' => $id,
                'version' => 1,
                'content' => $json,
                'source' => 'install',
                'created_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // The shipped header shows instead; the merchant writes the line in the editor.
            Log::warning('The store\'s announcement line could not be carried into the theme.', ['error' => $e->getMessage()]);
        }
    }
};
