<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Many API keys, each permitted only what it needs.
 *
 * Replaces the single settings row (api.erp_token) that every integration had
 * to share, which meant one key for every purpose and no way to withdraw one
 * without breaking the rest.
 *
 * Keys are HASHED, not encrypted. The old row was encrypted, which is
 * reversible: anyone holding the database and APP_KEY could read every token
 * back in plaintext. A hash cannot be read back even by Venta, and nothing is
 * lost by it, because a key was already only ever shown once at creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            // The first characters, kept in the clear so a person can tell two
            // keys apart in a list without anything usable being stored.
            $table->string('prefix', 16)->index();
            $table->string('key_hash');
            // Which areas this key may read and write, e.g.
            // {"catalogue":["read"],"orders":["read","write"]}
            $table->json('abilities');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // The existing token becomes a key with everything ticked, so the ERP
        // already using it notices nothing. Without this the change is an
        // outage on every live store, which is not a trade worth making for a
        // tidier table.
        $stored = Setting::where('group', 'api')->where('key', 'erp_token')->value('value');

        if (! $stored) {
            return;
        }

        try {
            $plain = Crypt::decryptString($stored);
        } catch (\Throwable) {
            $plain = $stored; // never encrypted on this install
        }

        DB::table('api_keys')->insert([
            'label' => 'ERP',
            'prefix' => substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
            'abilities' => json_encode([
                'catalogue' => ['read', 'write'],
                'orders' => ['read', 'write'],
                'fulfillment' => ['read', 'write'],
                'reviews' => ['read', 'write'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The old row is deliberately LEFT IN PLACE. Deleting it here would
        // make this migration unrollable in practice: down() could recreate
        // the table but never the token, and every integration would be locked
        // out with no way back. It is ignored from now on and can be removed
        // by hand once the new screen is trusted.
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
