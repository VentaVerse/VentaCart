<?php

namespace App\Services\PaymentMethods;

use App\Models\BankAccount;
use App\Models\Order;
use App\Services\ThemeService;

class BankTransferPayment implements PaymentMethodInterface
{
    private ?int $bankAccountId = null;
    private ?BankAccount $bankAccount = null;

    public function __construct(?int $bankAccountId = null)
    {
        $this->bankAccountId = $bankAccountId;
        if ($bankAccountId) {
            $this->bankAccount = BankAccount::find($bankAccountId);
        }
    }

    public function getKey(): string
    {
        if ($this->bankAccountId) {
            return 'bank_transfer_' . $this->bankAccountId;
        }
        return 'bank_transfer';
    }

    public function getBaseKey(): string
    {
        return 'bank_transfer';
    }

    public function getName(): string
    {
        // Account instance with a resolvable account: bank name alone (e.g. "Maya").
        if ($this->bankAccount) {
            return $this->bankAccount->bank_name;
        }
        // Account row missing (deleted after the key was persisted, e.g. on an
        // order) or a keyless/family instance: fall back to the family label.
        return $this->getDisplayPrefix();
    }

    private function getDisplayPrefix(): string
    {
        $custom = \App\Models\Setting::where('group', 'payment')
            ->where('key', 'bank_transfer_display_name')
            ->value('value');
        return !empty($custom) ? $custom : 'Bank Transfer';
    }

    public function getDescription(): string
    {
        if ($this->bankAccount) {
            $desc = $this->bankAccount->account_name . ' | ' . $this->bankAccount->account_number;
            if ($this->bankAccount->instructions) {
                $desc .= '. ' . $this->bankAccount->instructions;
            }
            return $desc;
        }
        return 'Pay via bank transfer. Order will be confirmed after payment is verified.';
    }

    public function getImage(): ?string
    {
        if ($this->bankAccount && $this->bankAccount->qr_image) {
            return storage_url($this->bankAccount->qr_image);
        }
        return null;
    }

    public function isEnabled(): bool
    {
        return (bool) \App\Models\Setting::where('group', 'payment')->where('key', 'bank_transfer_enabled')->value('value');
    }

    public function processPayment(Order $order): PaymentResult
    {
        return new PaymentResult(success: true);
    }

    /**
     * The Bank Accounts CRUD is a method FACTORY: one first-class payment
     * method per active account. No accounts => no bank methods (the old
     * generic fallback is gone — a method with no account details was a
     * phantom). Family enable (bank_transfer_enabled) gates the whole factory.
     *
     * @return BankTransferPayment[]
     */
    public static function instances(): array
    {
        $family = new self();
        if (!$family->isEnabled()) {
            return [];
        }

        return BankAccount::active()
            ->orderBy('sort_order')->orderBy('bank_name')
            ->get()
            ->map(fn ($account) => new self($account->id))
            ->all();
    }

    /**
     * Resolve bank account details for displaying in order confirmation.
     */
    public static function getInstructionsForOrder(Order $order): ?string
    {
        $method = $order->payment_method;

        // Check for specific bank account (bank_transfer_N)
        if (preg_match('/^bank_transfer_(\d+)$/', $method, $matches)) {
            $account = BankAccount::find((int) $matches[1]);
            if ($account) {
                $lines = [];
                $lines[] = 'Bank: ' . $account->bank_name;
                $lines[] = 'Account Name: ' . $account->account_name;
                $lines[] = 'Account Number: ' . $account->account_number;
                if ($account->instructions) {
                    $lines[] = '';
                    $lines[] = $account->instructions;
                }
                return implode("\n", $lines);
            }
        }

        // Fallback for generic 'bank_transfer'
        if ($method === 'bank_transfer') {
            return theme('payment.bank_transfer_instructions');
        }

        return null;
    }
}
