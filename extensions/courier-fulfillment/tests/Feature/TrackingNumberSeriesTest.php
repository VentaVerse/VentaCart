<?php

namespace Tests\Feature\CourierFulfillment;

require_once __DIR__ . '/LbcFixtures.php';

use Extensions\CourierFulfillment\Models\TrackingNumber;
use Extensions\CourierFulfillment\TrackingNumbers\TrackingNumberSeriesException;
use Extensions\CourierFulfillment\TrackingNumbers\TrackingNumberSeriesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Numbers from an assigned block: in order, never outside it, never twice,
 * and back in the pool only when the courier definitely saved nothing.
 */
class TrackingNumberSeriesTest extends TestCase
{
    use LbcFixtures;
    use RefreshDatabase;

    private const FIRST = 10000000000001;

    private const LAST = 10000000000005;

    private TrackingNumberSeriesService $series;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLbc();
        $this->series = new TrackingNumberSeriesService();
    }

    private function reserve(): int
    {
        return $this->series->reserve('lbc', self::FIRST, self::LAST)->number;
    }

    public function test_numbers_are_issued_in_order_from_the_first(): void
    {
        $this->assertSame([self::FIRST, self::FIRST + 1, self::FIRST + 2], [$this->reserve(), $this->reserve(), $this->reserve()]);
        $this->assertSame(3, TrackingNumber::where('status', TrackingNumber::RESERVED)->count());
    }

    public function test_the_block_end_refuses(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $last = $this->reserve();
        }
        $this->assertSame(self::LAST, $last);

        try {
            $this->reserve();
            $this->fail('A number past the block end was issued.');
        } catch (TrackingNumberSeriesException $e) {
            $this->assertStringContainsString('used up', $e->getMessage());
        }

        $this->assertSame(5, TrackingNumber::count());
    }

    public function test_it_warns_near_the_end(): void
    {
        Log::spy();

        $this->assertFalse($this->series->reserve('lbc', self::FIRST, self::LAST)->nearEnd);
        $this->reserve();
        $this->reserve();
        $fourth = $this->series->reserve('lbc', self::FIRST, self::LAST);

        // A five-number block warns with one number (20 percent) left.
        $this->assertSame(1, $fourth->remaining);
        $this->assertTrue($fourth->nearEnd);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'running out'))->atLeast()->once();
    }

    public function test_a_spent_number_is_never_issued_again(): void
    {
        $issued = $this->series->reserve('lbc', self::FIRST, self::LAST);
        $this->series->spend($issued, 'Unknown: LBC did not answer');

        $this->assertSame(self::FIRST + 1, $this->reserve());
        $this->assertSame(TrackingNumber::SPENT, TrackingNumber::where('number', self::FIRST)->value('status'));
    }

    public function test_a_released_number_is_issued_next(): void
    {
        $first = $this->series->reserve('lbc', self::FIRST, self::LAST);
        $second = $this->series->reserve('lbc', self::FIRST, self::LAST);
        $this->series->spend($second, 'Accepted');
        $this->series->release($first, 'Rejected: code 107');

        $this->assertSame(self::FIRST, $this->series->peek('lbc', self::FIRST, self::LAST)->number);
        $this->assertSame(self::FIRST, $this->reserve());
        $this->assertSame(self::FIRST + 2, $this->reserve());
    }

    public function test_a_number_settles_once(): void
    {
        $issued = $this->series->reserve('lbc', self::FIRST, self::LAST);
        $this->series->spend($issued, 'Accepted');

        $this->expectException(TrackingNumberSeriesException::class);
        $this->series->release($issued, 'Rejected');
    }

    public function test_peek_writes_nothing(): void
    {
        $this->assertSame(self::FIRST, $this->series->peek('lbc', self::FIRST, self::LAST)->number);
        $this->assertSame(self::FIRST, $this->series->peek('lbc', self::FIRST, self::LAST)->number);

        $this->assertSame(0, DB::table('courier_fulfillment_tracking_number_series')->count());
        $this->assertSame(0, TrackingNumber::count());
    }

    public function test_a_changed_block_end_or_an_overlapping_block_refuses(): void
    {
        $this->reserve();

        try {
            $this->series->reserve('lbc', self::FIRST, self::LAST + 100);
            $this->fail('A changed block end was accepted.');
        } catch (TrackingNumberSeriesException) {
        }

        $this->expectException(TrackingNumberSeriesException::class);
        $this->series->reserve('lbc', self::FIRST + 2, self::LAST + 10);
    }

    public function test_couriers_keep_separate_series(): void
    {
        $this->assertSame(self::FIRST, $this->reserve());
        $this->assertSame(self::FIRST, $this->series->reserve('other', self::FIRST, self::LAST)->number);
    }

    /**
     * Four processes claim from one block at once, through a database file
     * they all share. Every number they got must be different, and together
     * they must be the first numbers of the block with no gap.
     */
    public function test_concurrent_claims_never_share_a_number(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is needed to run claims in parallel.');
        }

        $dir = sys_get_temp_dir() . '/tn-series-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $file = $dir . '/race.sqlite';
        touch($file);

        config(['database.connections.race' => [
            'driver' => 'sqlite',
            'database' => $file,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 10000,
            'journal_mode' => 'wal',
        ]]);

        $this->createSeriesTables('race');
        DB::purge('race');

        $workers = 4;
        $perWorker = 15;
        $pids = [];

        for ($w = 0; $w < $workers; $w++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                // Child: its own connection, its own results file, and no
                // shutdown handlers from the parent test run.
                DB::purge('race');
                $service = new TrackingNumberSeriesService('race');
                $got = [];
                for ($i = 0; $i < $perWorker; $i++) {
                    for ($try = 0; $try < 50; $try++) {
                        try {
                            $got[] = $service->reserve('lbc', 20000000000001, 20000000001000)->number;
                            break;
                        } catch (\Throwable) {
                            usleep(random_int(1000, 5000));
                        }
                    }
                }
                file_put_contents("{$dir}/worker-{$w}.json", json_encode($got));
                posix_kill(posix_getpid(), SIGKILL);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $all = [];
        for ($w = 0; $w < $workers; $w++) {
            $all = array_merge($all, json_decode((string) @file_get_contents("{$dir}/worker-{$w}.json"), true) ?? []);
        }

        DB::purge('race');
        $rows = DB::connection('race')->table('courier_fulfillment_tracking_numbers')->count();
        $lastIssued = DB::connection('race')->table('courier_fulfillment_tracking_number_series')->value('last_issued_number');

        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);

        $this->assertCount($workers * $perWorker, $all, 'Some claims never succeeded.');
        $this->assertSame(count($all), count(array_unique($all)), 'Two claims got the same number.');
        sort($all);
        $this->assertSame(range(20000000000001, 20000000000000 + count($all)), $all);
        $this->assertSame(count($all), $rows);
        $this->assertSame(20000000000000 + count($all), (int) $lastIssued);
    }

    /** The two tables, as the extension's migration creates them, on another connection. */
    private function createSeriesTables(string $connection): void
    {
        $schema = Schema::connection($connection);

        $schema->create('courier_fulfillment_tracking_number_series', function (Blueprint $table) {
            $table->id();
            $table->string('courier', 32);
            $table->unsignedBigInteger('first_number');
            $table->unsignedBigInteger('last_number');
            $table->unsignedBigInteger('last_issued_number')->nullable();
            $table->timestamps();
            $table->unique(['courier', 'first_number']);
        });

        $schema->create('courier_fulfillment_tracking_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id');
            $table->unsignedBigInteger('number');
            $table->string('status', 16);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('outcome', 255)->nullable();
            $table->timestamps();
            $table->unique(['series_id', 'number']);
        });
    }
}
