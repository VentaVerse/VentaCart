<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Billing address (nullable -- only when different from shipping)
            $table->string('billing_first_name')->nullable()->after('tracking_number');
            $table->string('billing_last_name')->nullable()->after('billing_first_name');
            $table->text('billing_address_1')->nullable()->after('billing_last_name');
            $table->string('billing_city')->nullable()->after('billing_address_1');
            $table->string('billing_district')->nullable()->after('billing_city');
            $table->string('billing_barangay')->nullable()->after('billing_district');
            $table->string('billing_state')->nullable()->after('billing_barangay');
            $table->string('billing_postcode')->nullable()->after('billing_state');
            $table->string('billing_country_code', 3)->nullable()->after('billing_postcode');
        });

        // Insert checkout/mail/payment settings
        $now = now();
        $settings = [
            ['group' => 'checkout', 'key' => 'guest_allowed', 'value' => '0', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'checkout', 'key' => 'shipping_flat_rate', 'value' => '150', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'checkout', 'key' => 'shipping_free_threshold', 'value' => '3000', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mail', 'key' => 'driver', 'value' => 'smtp', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mail', 'key' => 'host', 'value' => '', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mail', 'key' => 'port', 'value' => '587', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mail', 'key' => 'username', 'value' => '', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mail', 'key' => 'password', 'value' => '', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mail', 'key' => 'encryption', 'value' => 'tls', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mail', 'key' => 'from_address', 'value' => '', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'mail', 'key' => 'from_name', 'value' => '', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'payment', 'key' => 'bank_transfer_enabled', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['group' => 'payment', 'key' => 'bank_transfer_instructions', 'value' => "Please transfer to the following bank account:\n\nBank: BDO\nAccount Name: New Gear Day\nAccount Number: 1234567890\n\nPlease include your order number as reference.", 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('settings')->insert($settings);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'billing_first_name',
                'billing_last_name',
                'billing_address_1',
                'billing_city',
                'billing_district',
                'billing_barangay',
                'billing_state',
                'billing_postcode',
                'billing_country_code',
            ]);
        });

        DB::table('settings')
            ->where(function ($query) {
                $query->where('group', 'checkout')
                    ->whereIn('key', ['guest_allowed', 'shipping_flat_rate', 'shipping_free_threshold']);
            })
            ->orWhere(function ($query) {
                $query->where('group', 'mail')
                    ->whereIn('key', ['driver', 'host', 'port', 'username', 'password', 'encryption', 'from_address', 'from_name']);
            })
            ->orWhere(function ($query) {
                $query->where('group', 'payment')
                    ->whereIn('key', ['bank_transfer_enabled', 'bank_transfer_instructions']);
            })
            ->delete();
    }
};
