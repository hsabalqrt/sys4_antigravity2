<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

use App\Events\ClientChange;
// use Livewire\Attributes\On;


class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    /**
     * يستمع لحدث تغيير العميل ويقوم بتحديث الصفحة.
     * 
     * @param: none
     * @returns: void
     */
    // #[On('echo:client-change,.ClientChange')]
    // public function clientChanged()
    // {
    //     $this->record->refresh();
    //     $this->fillForm();
    // }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Action::make('toggle_status')
                ->label($this->record->status ? '🔴 إيقاف العميل' : '🟢 تفعيل العميل')
                ->color($this->record->status ? 'danger' : 'success')
                ->action(function () {
                    $this->record->status = ! $this->record->status;
                    $this->record->save();

                    Notification::make()
                        ->title($this->record->status ? 'تم تفعيل العميل' : 'تم إيقاف العميل')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->icon($this->record->status ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle'),
        ];
    }

    public function getSubheading(): ?string
    {
        return $this->record->status ? '🟢 العميل شغال' : '🔴 العميل موقّف';
    }


    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user'] = Auth::id();
        if ($data['is_credit_allowed']) {
            $data['suspension_days'] = null;
        }
        if (($data['customer_rating_type'] ?? null) === 'automatic') {
            // احسب التقييم تلقائيًا
            // if (!empty($data['amount']) && !empty($data['packages']['package_original_price'])) {
            //     $data['customer_rating_value'] = round(
            //         ($data['amount'] / $data['packages']['package_original_price']) * 10
            //     );
            // }
            $amount = $data['amount'];

            // FIXME: Package model does not exist. This logic cannot run.
            // $packageId = $data['package_id'];
            // if ($amount && $packageId) {
            //     $package = \App\Models\Package::find($packageId);
            //     if ($package && $package->package_original_price != 0) {
            //         $data['customer_rating_value'] = round(($amount / $package->package_original_price) * 10);
            //     }
            // }
        }
        return $data;
    }
}
