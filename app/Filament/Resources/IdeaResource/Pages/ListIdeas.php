<?php

namespace App\Filament\Resources\IdeaResource\Pages;

use App\Filament\Resources\IdeaResource;
use App\Models\Tag;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View as ViewView;
use Illuminate\Support\Collection;

class ListIdeas extends ListRecords
{
    protected static string $resource = IdeaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
