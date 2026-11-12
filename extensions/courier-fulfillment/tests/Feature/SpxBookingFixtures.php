<?php

namespace Tests\Feature\CourierFulfillment;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Couriers\Spx\SpxFulfillment;
use Extensions\CourierFulfillment\PlacementMap;
use Tests\Support\FulfillmentSettings;
use Tests\Support\SpxFixtures;

/**
 * An SPX store ready to book: the extension installed, SPX switched on with
 * neutral credentials, a To Pack and a To Handover status, a pickup address
 * SPX lists, and every SPX answer faked (stray requests are refused).
 */
trait SpxBookingFixtures
{
    use SpxFixtures;

    protected OrderStatus $toPack;

    protected OrderStatus $handover;

    protected function setUpSpx(): void
    {
        Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();

        $this->spxCredentials();
        $this->spxFake();

        $this->toPack = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Processing'], ['sort_order' => 2]);
        PlacementMap::put($this->toPack->id, 'to_pack');
        FulfillmentSettings::selectFor('to_pack', (int) $this->toPack->id);

        $this->handover = OrderStatus::firstOrCreate(['store_id' => 1, 'name' => 'Order Being Shipped'], ['sort_order' => 4]);
        PlacementMap::put($this->handover->id, 'to_handover');
        FulfillmentSettings::selectFor('to_handover', (int) $this->handover->id);

        $this->spxPickupAddress();
    }

    protected function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    protected function spxOrder(array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'store_id' => Store::query()->value('id'),
            'order_status_id' => $this->toPack->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '09171234567',
            'payment_method' => 'bank_transfer',
            'payment_method_title' => 'Bank Transfer',
            'subtotal' => 1000,
            'shipping_cost' => 100,
            'total' => 1100,
        ], $this->spxDeliveryAddress(), $overrides));

        OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'Demo Item',
            'sku' => 'DEMO-001',
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
        ]);

        return $order;
    }

    protected function spxCodOrder(array $overrides = []): Order
    {
        Setting::updateOrCreate(['group' => 'courier', 'key' => 'cod_payment_methods'], ['value' => json_encode(['cod'])]);

        return $this->spxOrder(array_merge(['payment_method' => 'cod', 'payment_method_title' => 'Cash on Delivery'], $overrides));
    }

    protected function spx(): SpxFulfillment
    {
        return new SpxFulfillment(sleeper: function (int $ms): void {
        });
    }

    protected function queueUrl(string $suffix = ''): string
    {
        return '/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment' . $suffix;
    }

    protected function spxMeta(Order $order): array
    {
        return json_decode((string) Order::findOrFail($order->id)->courier_meta, true) ?: [];
    }

    /** The one order SPX was sent by 1.4 Create Order. */
    protected function createdOrder(): array
    {
        return $this->spxSent('batch_create_order')['orders'][0] ?? [];
    }
}
