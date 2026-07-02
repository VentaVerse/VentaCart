<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\PaymentManager;
use App\Services\PaymentMethods\PaymentMethodInterface;
use App\Services\PaymentMethods\PaymentResult;
use PHPUnit\Framework\TestCase;

class FakeSingle implements PaymentMethodInterface
{
    public function getKey(): string { return 'fake_single'; }
    public function getBaseKey(): string { return 'fake_single'; }
    public function getName(): string { return 'Fake Single'; }
    public function getDescription(): string { return ''; }
    public function getImage(): ?string { return null; }
    public function isEnabled(): bool { return true; }
    public function processPayment(Order $order): PaymentResult { return new PaymentResult(true); }
}

class FakeFactoryMethod extends FakeSingle
{
    public function __construct(private int $n = 0) {}
    public function getKey(): string { return 'fake_factory_' . $this->n; }
    public function getBaseKey(): string { return 'fake_factory'; }
    public function isEnabled(): bool { return $this->n !== 2; } // #2 disabled
    public static function instances(): array { return [new self(1), new self(2)]; }
}

class PaymentManagerTest extends TestCase
{
    protected function setUp(): void { PaymentManager::reset(); }

    public function test_single_class_registers_one_method(): void
    {
        PaymentManager::register(FakeSingle::class);
        $this->assertSame(['fake_single'], array_map(fn ($m) => $m->getKey(), PaymentManager::methods()));
    }

    public function test_factory_class_spreads_instances(): void
    {
        PaymentManager::register(FakeFactoryMethod::class);
        $this->assertSame(['fake_factory_1', 'fake_factory_2'], array_map(fn ($m) => $m->getKey(), PaymentManager::methods()));
    }

    public function test_enabled_methods_filters(): void
    {
        PaymentManager::register(FakeFactoryMethod::class);
        $this->assertSame(['fake_factory_1'], array_map(fn ($m) => $m->getKey(), PaymentManager::enabledMethods()));
    }

    public function test_get_by_exact_key(): void
    {
        PaymentManager::register(FakeSingle::class);
        PaymentManager::register(FakeFactoryMethod::class);
        $this->assertSame('fake_factory_2', PaymentManager::get('fake_factory_2')?->getKey());
        $this->assertNull(PaymentManager::get('nope'));
    }
}
