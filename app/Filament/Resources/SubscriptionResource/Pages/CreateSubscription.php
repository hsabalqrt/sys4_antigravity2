<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();

        if ($data['is_paid_now'] ?? false) {
            $this->record->transactions()->create([
                'client_id' => $this->record->client_id,
                'amount' => $data['paid_amount'] ?? $this->record->payment_amount,
                'type' => 'credit',
                'description' => $data['payment_note'] ?? ('سداد مقدم للاشتراك: ' . $this->record->subscription_type),
                'transaction_date' => $data['payment_date'] ?? now(),
            ]);
        }
    }
}
