<?php

namespace Extensions\StoreCredit\Console;

use App\Models\Customer;
use Extensions\StoreCredit\Models\CustomerTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time legacy import (card VENT-16): pulls OpenCart's
 * customer_transaction ledger into customer_transactions, preserving
 * signed amounts, descriptions, and dates. Customers are matched by
 * legacy_oc_id first (the migration's own mapping), email as fallback.
 * Refuses to run into a non-empty ledger unless --append is given, so
 * a re-run cannot silently double every balance.
 */
class ImportOpenCartCredit extends Command
{
    protected $signature = 'store-credit:import-oc
        {--host=127.0.0.1 : OpenCart MySQL host}
        {--port=3306 : OpenCart MySQL port}
        {--database= : OpenCart database name}
        {--username= : OpenCart MySQL user}
        {--password= : OpenCart MySQL password}
        {--prefix=oc_ : OpenCart table prefix}
        {--append : Allow importing into a ledger that already has rows}
        {--dry-run : Report what would be imported without writing}';

    protected $description = 'Import OpenCart customer_transaction balances into the store credit ledger';

    public function handle(): int
    {
        foreach (['database', 'username', 'password'] as $required) {
            if (! $this->option($required)) {
                $this->error("--{$required} is required.");

                return self::FAILURE;
            }
        }

        if (! $this->option('append') && CustomerTransaction::exists()) {
            $this->error('customer_transactions already has rows. Re-importing would double balances; pass --append only if you know the incoming rows are new.');

            return self::FAILURE;
        }

        config(['database.connections.oc_import' => [
            'driver' => 'mysql',
            'host' => $this->option('host'),
            'port' => $this->option('port'),
            'database' => $this->option('database'),
            'username' => $this->option('username'),
            'password' => $this->option('password'),
            'charset' => 'utf8mb4',
        ]]);

        $prefix = $this->option('prefix');
        $rows = DB::connection('oc_import')
            ->table($prefix . 'customer_transaction as t')
            ->join($prefix . 'customer as c', 'c.customer_id', '=', 't.customer_id')
            ->orderBy('t.customer_transaction_id')
            ->get(['t.customer_id', 't.description', 't.amount', 't.date_added', 'c.email']);

        $imported = 0;
        $skipped = [];

        foreach ($rows as $row) {
            $customer = Customer::where('legacy_oc_id', $row->customer_id)->first()
                ?? Customer::where('email', $row->email)->first();

            if (! $customer) {
                $skipped[$row->email] = true;
                continue;
            }

            if (! $this->option('dry-run')) {
                CustomerTransaction::create([
                    'customer_id' => $customer->id,
                    'order_id' => null,
                    'created_by' => null,
                    'description' => $row->description !== '' ? $row->description : 'Imported from OpenCart',
                    'amount' => (float) $row->amount,
                    'created_at' => $row->date_added,
                ]);
            }
            $imported++;
        }

        $mode = $this->option('dry-run') ? 'Would import' : 'Imported';
        $this->info("{$mode} {$imported} of {$rows->count()} ledger rows.");
        if ($skipped !== []) {
            $this->warn(count($skipped) . ' OC customers had no matching Venta customer (by legacy_oc_id or email): ' . implode(', ', array_slice(array_keys($skipped), 0, 10)) . (count($skipped) > 10 ? ', ...' : ''));
        }

        return self::SUCCESS;
    }
}
