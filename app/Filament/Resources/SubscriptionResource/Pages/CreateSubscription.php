<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use App\Filament\Traits\PaymentFormHelpers;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscription extends CreateRecord {
    use PaymentFormHelpers;
    protected static string $resource = SubscriptionResource::class;

    protected function afterCreate(): void {
        $data = $this->form->getRawState();

        if ($data['is_paid_now'] ?? false) {
            $payload = self::buildTransactionPayload(
                $this->record,
                $data,
                'سداد مقدم للاشتراك: ' . self::subscriptionTypeLabel($this->record->subscription_type),
                amountKey: 'paid_amount',
                type: 'credit'
            );
            $this->record->transactions()->create($payload);
        }
    }
}
