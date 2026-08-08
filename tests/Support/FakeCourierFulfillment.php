<?php

namespace Tests\Support;

use App\Models\Order;
use Extensions\CourierFulfillment\Contracts\CourierFulfillmentInterface;

/**
 * Minimal second courier, registered only inside tests
 * (CourierManager::register(FakeCourierFulfillment::class)), to prove the
 * settings page and CourierSettingsController's allowlist are genuinely
 * schema-driven rather than QuadX-shaped — a real second courier (SPX, LBC)
 * must be able to drop in the same way without editing shared files.
 *
 * Every stored key is prefixed 'fake_' so a test can assert QuadX's and this
 * courier's settings round-trip independently of each other.
 */
class FakeCourierFulfillment implements CourierFulfillmentInterface
{
    public function getKey(): string
    {
        return 'fake';
    }

    public function getName(): string
    {
        return 'Fake Courier';
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function getServiceTypes(): array
    {
        return [['value' => 'standard', 'label' => 'Standard']];
    }

    public function getParcelOptions(): array
    {
        return [['value' => 'box', 'label' => 'Box']];
    }

    public function getShippingPaymentOptions(): array
    {
        return [['value' => 'net_off', 'label' => 'Bill me']];
    }

    public function createShipment(Order $order, array $opts): array
    {
        return ['success' => false, 'error' => 'not implemented'];
    }

    public function cancelShipment(string $trackingNumber): array
    {
        return ['success' => false, 'error' => 'not implemented'];
    }

    /** Never produces courier_status='creating' — nothing async to resolve. */
    public function resolvePendingCreation(Order $order): ?string
    {
        return null;
    }

    public function getTracking(string $trackingNumber): array
    {
        return ['success' => false, 'error' => 'not implemented'];
    }

    public function terminalStatuses(): array
    {
        return ['delivered', 'canceled'];
    }

    public function cancelableStatuses(): array
    {
        return ['pending'];
    }

    public function statusPhase(?string $status): array
    {
        return ['step' => 0, 'failed' => false];
    }

    public function printLabel(string $trackingNumber, string $format): array
    {
        return ['success' => false, 'error' => 'not implemented'];
    }

    public function estimateFee(Order $order, array $options): ?object
    {
        return null;
    }

    public function getPickupSlots(Order $order): array
    {
        return [];
    }

    public function parseWebhook(array $payload): array
    {
        return [];
    }

    /**
     * Deterministic fake link (independent of courier_meta shape), so a test
     * can register this courier, book/attach an order to it, and assert the
     * queue view genuinely loops CourierFulfillmentInterface::trackingLinks()
     * rather than parsing any one courier's courier_meta shape itself.
     */
    public function trackingLinks(Order $order): array
    {
        return [['label' => 'Track', 'url' => 'https://fake-courier.test/track/' . $order->id]];
    }

    public function testConnection(): array
    {
        return ['ok' => false, 'message' => 'not implemented'];
    }

    public function refreshStatusLabels(): array
    {
        return ['ok' => false, 'error' => 'not implemented'];
    }

    public function mappableStatuses(): array
    {
        return ['pending', 'delivered'];
    }

    /**
     * One field of each schema type, plus a mode-scoped pair, so a single
     * FakeCourier registration exercises the settings page's whole rendering
     * path (toggle/select/text/password, mode grouping, encryption+masking).
     */
    public function settingsSchema(): array
    {
        return [
            [
                'key'   => 'fake_enabled',
                'label' => 'Enable Fake Courier',
                'type'  => 'toggle',
            ],
            [
                'key'     => 'fake_mode',
                'label'   => 'Mode',
                'type'    => 'select',
                'options' => [
                    ['value' => 'staging',    'label' => 'Staging'],
                    ['value' => 'production', 'label' => 'Production'],
                ],
            ],
            [
                'key'         => 'fake_staging_token',
                'label'       => 'Token',
                'type'        => 'password',
                'encrypted'   => true,
                'mode_scoped' => 'staging',
            ],
            [
                'key'         => 'fake_production_token',
                'label'       => 'Token',
                'type'        => 'password',
                'encrypted'   => true,
                'mode_scoped' => 'production',
            ],
            [
                'key'   => 'fake_note',
                'label' => 'Note',
                'type'  => 'text',
                'help'  => 'Plain, non-secret field.',
            ],
        ];
    }
}
