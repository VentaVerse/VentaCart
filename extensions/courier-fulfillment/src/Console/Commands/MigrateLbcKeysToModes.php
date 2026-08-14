<?php

namespace Extensions\CourierFulfillment\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * One-off carry-over of LBC's four single-environment subscription keys into
 * the mode-scoped fields that replaced them.
 *
 * WHY THE TEST HALF. The keys already on file are the owner's SECONDARY
 * subscription keys, and LBC treats the secondary key of a subscription as
 * its test environment (docs/lbc/integration-notes.md, verified live
 * 2026-08-14). The original fields predated that finding and were neither
 * live nor test; the values in them are test keys, so that is where they
 * land. Nothing is re-typed and nothing is re-encrypted: the stored
 * ciphertext is moved verbatim under the new key name, so it decrypts to
 * exactly what it decrypted to before.
 *
 * A COMMAND, not a data migration, on purpose: a fresh install has no legacy
 * LBC settings to move and must stay clean, so repairing an EXISTING store is
 * an explicit thing an operator runs once (house rule, see
 * feedback_no_install_specific_migrations).
 *
 * Idempotent. A destination that already holds a value is never overwritten
 * (the operator's own paste wins over a stale legacy row), a missing source
 * is a no-op, and a value that will not decrypt is reported and LEFT WHERE IT
 * IS rather than moved: a ciphertext encrypted under a previous APP_KEY is
 * already lost, and moving it would only hide that.
 */
class MigrateLbcKeysToModes extends Command
{
    protected $signature = 'fulfillment:lbc-migrate-keys {--dry-run : Report what would move without writing anything}';

    protected $description = 'Move the pre-mode LBC subscription keys into their Test-mode fields, once.';

    /**
     * Retired key => the mode-scoped key that replaced it. Every value on the
     * left is a secondary (test) key, hence every destination is a test one.
     * `lbc_psgc_key` becomes the Common API key: the same subscription, under
     * the name LBC's own product catalogue uses.
     */
    private const MOVES = [
        'lbc_psgc_key'       => 'lbc_test_common_key',
        'lbc_airwaybill_key' => 'lbc_test_airwaybill_key',
        'lbc_pickup_key'     => 'lbc_test_pickup_key',
        'lbc_track_key'      => 'lbc_test_track_key',
    ];

    /**
     * Keys with no destination at all. The Pickup Request AppKey was inferred
     * from a sample payload and disproven: a pickup was created with the
     * <AppKey> node removed entirely, authenticating on the
     * Ocp-Apim-Subscription-Key header alone. Any row is dead weight.
     */
    private const RETIRED = ['lbc_pickup_app_key'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stored = Setting::query()
            ->where('group', 'courier')
            ->whereIn('key', array_merge(
                array_keys(self::MOVES),
                array_values(self::MOVES),
                self::RETIRED
            ))
            ->pluck('value', 'key');

        $moves = [];
        foreach (self::MOVES as $from => $to) {
            $value = (string) ($stored[$from] ?? '');
            if ($value === '') {
                continue;
            }

            if ((string) ($stored[$to] ?? '') !== '') {
                $this->warn($to . ' already has a value. Left alone, and ' . $from . ' left where it is.');
                continue;
            }

            // Proof the move is safe before it happens: a key that cannot be
            // read here would be just as unreadable under its new name, and
            // the operator needs to know now rather than at booking time.
            try {
                $plain = Crypt::decryptString($value);
            } catch (\Throwable) {
                $this->error($from . ' will not decrypt (encrypted under a different APP_KEY?). Left where it is.');
                continue;
            }

            $moves[$from] = $to;
            $this->line(($dryRun ? 'Would move ' : 'Moved ') . $from . ' -> ' . $to
                . ' (ends ' . substr($plain, -4) . ')');
        }

        $retired = array_values(array_filter(
            self::RETIRED,
            fn (string $key) => array_key_exists($key, $stored->all())
        ));

        if ($moves === [] && $retired === []) {
            $this->line('No pre-mode LBC keys found. Nothing moved.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            foreach ($retired as $key) {
                $this->line('Would delete ' . $key . ' (no longer used).');
            }

            return self::SUCCESS;
        }

        DB::transaction(function () use ($moves, $retired, $stored) {
            foreach ($moves as $from => $to) {
                Setting::updateOrCreate(
                    ['group' => 'courier', 'key' => $to],
                    ['value' => $stored[$from]]
                );
            }

            Setting::query()
                ->where('group', 'courier')
                ->whereIn('key', array_merge(array_keys($moves), $retired))
                ->delete();
        });

        foreach ($retired as $key) {
            $this->line('Deleted ' . $key . ' (no longer used).');
        }

        $this->info(count($moves) . ' LBC key(s) now live in Test mode, which is where secondary keys belong.');

        return self::SUCCESS;
    }
}
