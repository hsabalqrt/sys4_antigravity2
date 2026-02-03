<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ClientResource\Widgets\ClientCount;


// use Livewire\Attributes\On;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientCount::class,
        ];
    }

    /**
     * يستمع لحدث تغيير العميل ويقوم بتحديث الصفحة.
     * 
     * @param: none
     * @returns: void
     */
    // #[On('echo:client-change,.ClientChange')]
    // public function clientChanged()
    // {
    //     $this->dispatch('refreshTable');
    // }        
}
