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

    public function getName(): string
    {
        if ($this->bankAccount) {
            return 'Bank Transfer — ' . $this->bankAccount->bank_name;
        }
        return 'Bank Transfer';
    }

    public function getDescription(): string
    {
        if ($this->bankAccount) {
            $desc = $this->bankAccount->account_name . ' | ' . $this->bankAccount->account_number;
            if ($this->bankAccount->instructions) {
                $desc .= ' — ' . $this->bankAccount->instructions;
            }
            return $desc;
        }
        return 'Pay via bank transfer. Order will be confirmed after payment is verified.';
    }

    public function isEnabled(): bool
    {
        return (bool) theme('payment.bank_transfer_enabled');
    }

    public function processPayment(Order $order): PaymentResult
    {
        return new PaymentResult(success: true);
    }

    /**
     * Get all bank transfer payment options.
     * If bank_accounts table has active entries, returns one per account.
     * Otherwise falls back to a single generic "Bank Transfer" option.
     *
     * @return BankTransferPayment[]
     */
    public static function getPaymentOptions(): array
    {
        $accounts = BankAccount::active()->orderBy('sort_order')->orderBy('bank_name')->get();

        if ($accounts->isNotEmpty()) {
            $options = [];
            foreach ($accounts as $account) {
                $options[] = new self($account->id);
            }
            return $options;
        }

        // Fallback: single generic bank transfer
        return [new self()];
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
