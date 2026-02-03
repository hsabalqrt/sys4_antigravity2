<?php

namespace App\Filament\Traits;

use App\Models\Currency;
use App\Models\Subscription;
use App\Models\Client;

trait PaymentFormHelpers {
    protected static function subscriptionTypeLabel($type): string {
        if (is_object($type) && method_exists($type, 'getLabel')) {
            return (string) $type->getLabel();
        }
        if (is_string($type) || is_int($type)) {
            $from = \App\Filament\Enums\SubscriptionType::tryFrom($type);
            return $from?->getLabel() ?? (string) $type;
        }
        return (string) $type;
    }
    protected static function computeExchangeRate(?int $paymentCurrencyId, ?int $targetCurrencyId): float {
        if (!$paymentCurrencyId || !$targetCurrencyId || $paymentCurrencyId === $targetCurrencyId) {
            return 1.0;
        }
        $pay = Currency::find($paymentCurrencyId);
        $tar = Currency::find($targetCurrencyId);
        if (!$pay || !$tar || !$pay->value || !$tar->value) {
            return 1.0;
        }
        return round(max($pay->value, $tar->value) / min($pay->value, $tar->value), 2);
    }

    protected static function convertAmount(?int $paymentCurrencyId, ?int $targetCurrencyId, float $originalAmount, float $exchangeRate): float {
        if (!$originalAmount || !$exchangeRate || !$paymentCurrencyId || !$targetCurrencyId || $paymentCurrencyId === $targetCurrencyId) {
            return round($originalAmount, 2);
        }
        $pay = Currency::find($paymentCurrencyId);
        $tar = Currency::find($targetCurrencyId);
        if (!$pay || !$tar) {
            return round($originalAmount, 2);
        }
        return round($pay->value > $tar->value ? ($originalAmount / $exchangeRate) : ($originalAmount * $exchangeRate), 2);
    }

    protected static function conversionHint(?int $paymentCurrencyId, ?int $targetCurrencyId): string {
        if (!$paymentCurrencyId || !$targetCurrencyId) return 'سيتم قيد المبلغ بعد التحويل حسب سعر الصرف.';
        if ($paymentCurrencyId === $targetCurrencyId) return 'نفس العملة، لا يوجد تحويل.';
        $pay = Currency::find($paymentCurrencyId);
        $tar = Currency::find($targetCurrencyId);
        if (!$pay || !$tar) return '';
        return ($pay->value > $tar->value)
            ? "سيتم تقسيم المبلغ على سعر الصرف من {$pay->currency} إلى {$tar->currency}."
            : "سيتم ضرب المبلغ في سعر الصرف من {$pay->currency} إلى {$tar->currency}.";
    }

    protected static function resolveTargetCurrencyId(?int $subscriptionId, ?int $clientId): ?int {
        if ($subscriptionId) return Subscription::find($subscriptionId)?->currency_id;
        if ($clientId) return Client::find($clientId)?->currency_id;
        return null;
    }

    protected static function buildTransactionPayload($target, array $data, string $description, string $amountKey = 'amount', string $type = 'credit'): array {
        return [
            'client_id' => $target->client_id,
            'amount' => $data[$amountKey],
            'original_amount' => $data['original_amount'] ?? $data[$amountKey],
            'currency_id' => $data['paid_currency_id'] ?? $data['currency_id'] ?? $target->currency_id,
            'exchange_rate' => $data['exchange_rate'] ?? 1,
            'type' => $type,
            'payment_gateway' => $data['payment_gateway'] ?? 'cash',
            'transfer_number' => $data['transfer_number'] ?? null,
            'description' => $data['payment_note'] ?? $data['note'] ?? $description,
            'transaction_date' => $data['payment_date'] ?? now(),
        ];
    }
}
