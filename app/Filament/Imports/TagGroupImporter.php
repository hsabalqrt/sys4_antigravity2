<?php

namespace App\Filament\Imports;

use App\Models\TagGroup;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;

class TagGroupImporter extends Importer
{
    protected static ?string $model = TagGroup::class;

    // public static function getOptionsFormComponents(): array
    // {
    //     return [
    //         Checkbox::make('authUserId')
    //             ->label('Update existing records'),
    //     ];
    // }

    // protected function beforeCreate(): void
    // {
    //     $options = $this->getOptions();

    //     if (isset($options['authUserId'])) {
    //         $this->data['added_by_user'] = $options['authUserId'];
    //     }
    // }

    protected function beforeSave(): void
    {
        $options = $this->getOptions();

        if (isset($options['authUserId'])) {
            $this->record->added_by_user = $options['authUserId'];
            $this->record->updated_by_user = $options['authUserId'];
        }
    }

    public function resolveRecord(): ?TagGroup
    {
        return TagGroup::firstOrNew([
            'name' => $this->data['name'],
        ]);
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:100']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your tag group import has completed and ' .
            number_format($import->successful_rows) . ' ' .
            str('row')->plural($import->successful_rows) . ' imported.';

        if ($failed = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failed) . ' ' .
                str('row')->plural($failed) . ' failed to import.';
        }

        return $body;
    }
}
