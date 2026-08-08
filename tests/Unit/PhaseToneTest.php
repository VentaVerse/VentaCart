<?php

namespace Tests\Unit;

use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Support\PhaseTone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Task W1 (fulfillment-workflow-v2 plan) + fulfillment-tabs-v3 Task 2: the
 * common fulfillment status spine's tone map. Table from
 * docs/superpowers/specs/2026-08-08-fulfillment-workflow-v2-design.md Part 1
 * (the original bucket name was renamed to `to_pack` by Task 2, per
 * docs/superpowers/specs/2026-08-08-fulfillment-tabs-v3-design.md) — success
 * spent ONLY on to_pack, delivered is neutral (not green), failed/cancelled
 * are danger, returning/unpaid are warning (both "chaseable" per the ERP
 * tone philosophy), everything else (the courier has it, or is creating the
 * shipment) is info.
 *
 * Extends the Laravel TestCase (not a plain PHPUnit one) purely so the
 * extension's runtime PSR-4 autoloader is registered before
 * Extensions\CourierFulfillment\Support\PhaseTone is referenced — that
 * namespace isn't in composer.json's autoload map by design (see
 * ExtensionManager::registerAutoloader()'s docblock); PhaseTone itself needs
 * no database access.
 */
class PhaseToneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
    }

    public static function phaseToneProvider(): array
    {
        return [
            'unpaid is warning (chaseable)'    => ['unpaid', 'warning'],
            'to_pack is the only accent'       => ['to_pack', 'success'],
            'booking is info (in flight)'      => ['booking', 'info'],
            'to_handover is info'              => ['to_handover', 'info'],
            'in_transit is info'               => ['in_transit', 'info'],
            'delivered is neutral, not green'  => ['delivered', 'neutral'],
            'failed is danger'                 => ['failed', 'danger'],
            'cancelled is danger'              => ['cancelled', 'danger'],
            'returning is warning (chaseable)' => ['returning', 'warning'],
        ];
    }

    #[DataProvider('phaseToneProvider')]
    public function test_tone_for_each_spine_phase(string $phase, string $expectedTone): void
    {
        $this->assertSame($expectedTone, PhaseTone::toneFor($phase));
    }

    public function test_success_is_spent_on_exactly_one_phase(): void
    {
        $successPhases = array_filter(
            PhaseTone::PHASES,
            fn (string $phase) => PhaseTone::toneFor($phase) === 'success'
        );

        $this->assertSame(['to_pack'], array_values($successPhases));
    }

    public function test_unknown_or_null_phase_falls_back_to_neutral(): void
    {
        $this->assertSame('neutral', PhaseTone::toneFor('some_future_phase_nobody_mapped_yet'));
        $this->assertSame('neutral', PhaseTone::toneFor(null));
        $this->assertSame('neutral', PhaseTone::toneFor(''));
    }

    /**
     * Rename totality (fulfillment-tabs-v3 Task 2): the spine's exact
     * membership, byte-for-byte — proves the pre-Task-2 bucket name is gone
     * (an exact-list assertSame() catches an extra/leftover entry that a
     * mere assertContains() on the replacement wouldn't), `to_pack` is its
     * exact replacement, and the new `unpaid` bucket (stage-level, never
     * returned by any courier's own statusPhase()) is present exactly once.
     */
    public function test_phases_constant_lists_exactly_the_nine_spine_buckets(): void
    {
        $this->assertSame(
            ['unpaid', 'to_pack', 'booking', 'to_handover', 'in_transit', 'delivered', 'failed', 'cancelled', 'returning'],
            PhaseTone::PHASES
        );
        $this->assertSame(9, count(PhaseTone::PHASES));
        $this->assertSame(9, count(array_unique(PhaseTone::PHASES)), 'every bucket must be unique');
    }
}
