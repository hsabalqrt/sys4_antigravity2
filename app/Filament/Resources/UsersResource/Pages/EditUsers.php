<?php

namespace App\Filament\Resources\UsersResource\Pages;

use App\Filament\Resources\UsersResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;


class EditUsers extends EditRecord
{
    protected static string $resource = UsersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getValidationRules(?User $record = null): array
    {
        $uniqueUsernameRule = $record ? Rule::unique('users', 'username')->ignore($record->id) : Rule::unique('users', 'username');
        $uniqueEmailRule = $record ? Rule::unique('users', 'email')->ignore($record->id) : Rule::unique('users', 'email');
    
        return [
            'username' => [
                'required',
                $uniqueUsernameRule,
            ],
            'email' => [
                'required',
                'email',
                $uniqueEmailRule,
            ],
            // ... other validation rules ...
        ];
    }

}
