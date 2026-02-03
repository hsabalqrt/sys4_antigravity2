<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

use Livewire\Attributes\On;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected static ?string $title = 'تفاصيل العميل';

    /**
     * يستمع لحدث تغيير العميل ويقوم بتحديث الصفحة.
     * 
     * @param: none
     * @returns: void
     */
    #[On('echo:client-change,.ClientChange')]
    public function clientChanged()
    {
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        return $this->record->status ? '🟢 العميل شغال' : '🔴 العميل موقّف';
    }
}
