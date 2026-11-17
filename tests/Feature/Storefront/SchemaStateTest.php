<?php

namespace Tests\Feature\Storefront;

use App\Support\SchemaState;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Table checks on the request path ask the database once, not on every
 * request: a table that exists is remembered, a missing one never is, and
 * migrations forget what was remembered.
 */
class SchemaStateTest extends TestCase
{
    use RefreshDatabase;

    private int $schemaQueries = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SchemaState::forget();
        DB::listen(function ($q) {
            if (str_contains($q->sql, 'sqlite_master') || str_contains($q->sql, 'information_schema') || str_contains($q->sql, 'pragma_table_list')) {
                $this->schemaQueries++;
            }
        });
    }

    public function test_a_table_that_exists_is_asked_about_once(): void
    {
        $this->assertTrue(SchemaState::hasTable('products'));
        $before = $this->schemaQueries;

        SchemaState::forgetInProcess();
        $this->assertTrue(SchemaState::hasTable('products'));
        $this->assertTrue(SchemaState::hasTable('products'));

        $this->assertSame($before, $this->schemaQueries, 'later checks, even in a fresh process, come from the cache');
    }

    public function test_a_missing_table_is_asked_about_every_time_so_it_is_seen_once_created(): void
    {
        $this->assertFalse(SchemaState::hasTable('demo_widgets'));
        Schema::create('demo_widgets', fn ($table) => $table->id());

        $this->assertTrue(SchemaState::hasTable('demo_widgets'));
    }

    public function test_migrations_forget_what_was_remembered(): void
    {
        Schema::create('demo_widgets', fn ($table) => $table->id());
        $this->assertTrue(SchemaState::hasTable('demo_widgets'));
        Schema::drop('demo_widgets');

        event(new MigrationsEnded('down'));

        $this->assertFalse(SchemaState::hasTable('demo_widgets'));
    }

    public function test_a_storefront_request_runs_no_table_checks_once_warm(): void
    {
        $this->get('/no-such-page-xyz');
        $this->schemaQueries = 0;

        $this->get('/no-such-page-xyz');
        $this->get('/');

        $this->assertSame(0, $this->schemaQueries);
    }
}
