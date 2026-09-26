<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A template speaks for exactly one order status.
 *
 * A template covering several statuses could only fill {order_status} with
 * one of them, so the preview and the text disagreed with the merchant's
 * intent. The status moves from a pivot onto the template itself, and a
 * template that covered several becomes one template per status, each with
 * the same message, so no status stops texting.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Read the old links before touching the table: altering it can
        // rebuild it (SQLite does), and a rebuild cascades through the
        // pivot's foreign key and empties it before it could be read.
        $pairs = Schema::hasTable('sms_template_statuses')
            ? DB::table('sms_template_statuses')
                ->join('order_statuses', 'order_statuses.id', '=', 'sms_template_statuses.order_status_id')
                ->join('sms_templates', 'sms_templates.id', '=', 'sms_template_statuses.sms_template_id')
                ->orderBy('sms_template_statuses.sms_template_id')
                ->orderBy('order_statuses.sort_order')
                ->orderBy('order_statuses.id')
                ->get([
                    'sms_templates.id as template_id', 'sms_templates.title', 'sms_templates.message',
                    'order_statuses.id as status_id', 'order_statuses.name as status_name',
                ])
            : collect();

        if (Schema::hasTable('sms_template_statuses')) {
            Schema::drop('sms_template_statuses');
        }

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->foreignId('order_status_id')->nullable()->unique()->after('id')
                ->constrained('order_statuses')->nullOnDelete();
        });

        DB::transaction(function () use ($pairs) {
            $seen = [];
            foreach ($pairs as $pair) {
                if (! isset($seen[$pair->template_id])) {
                    $seen[$pair->template_id] = true;
                    DB::table('sms_templates')->where('id', $pair->template_id)->update(['order_status_id' => $pair->status_id]);

                    continue;
                }

                DB::table('sms_templates')->insert([
                    'order_status_id' => $pair->status_id,
                    'title' => mb_substr($pair->title . ' (' . $pair->status_name . ')', 0, 100),
                    'message' => $pair->message,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::create('sms_template_statuses', function (Blueprint $table) {
            $table->foreignId('sms_template_id')->constrained('sms_templates')->cascadeOnDelete();
            $table->foreignId('order_status_id')->unique()->constrained('order_statuses')->cascadeOnDelete();
            $table->primary(['sms_template_id', 'order_status_id']);
        });

        foreach (DB::table('sms_templates')->whereNotNull('order_status_id')->get(['id', 'order_status_id']) as $row) {
            DB::table('sms_template_statuses')->insert(['sms_template_id' => $row->id, 'order_status_id' => $row->order_status_id]);
        }

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropForeign(['order_status_id']);
            $table->dropUnique(['order_status_id']);
            $table->dropColumn('order_status_id');
        });
    }
};
