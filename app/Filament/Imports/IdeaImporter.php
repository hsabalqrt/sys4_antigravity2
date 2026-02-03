<?php

namespace App\Filament\Imports;

use App\Models\Client;
use App\Models\Idea;
use App\Models\Location;
use App\Models\Tag;
use Carbon\Carbon;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class IdeaImporter extends Importer
{
    protected static ?string $model = Idea::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('content'),
            ImportColumn::make('description'),
            ImportColumn::make('repeat_for_clients')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('scheduled_at')->rules(['nullable', 'date'])
                ->castStateUsing(function ($state) {
                    $state = Carbon::parse($state)->format('Y-m-d H:i:s');
                    return $state;
                }),
            ImportColumn::make('idea_file'),
            ImportColumn::make('is_visible_in_generator')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),

            // أعمدة العلاقات (تجي كنص مفصول بفواصل)
            // أعمدة العلاقات كقيم نصية فقط (بدون relationship)
            ImportColumn::make('clients')->label('Clients'),
            ImportColumn::make('tags')->label('Tags'),
            ImportColumn::make('locations')->label('Locations'),
        ];
    }

    /**
     * استبعاد العلاقات من بيانات الحقول لإنشاء/تحديث النموذج الأساسي
     */
    protected function prepareData(array $row): array
    {
        return collect($row)->except(['clients', 'tags', 'locations'])->toArray();
    }

    /**
     * إنشاء أو تحديث سجل Idea بدون العلاقات
     */
    public function model(array $row)
    {
        $data = $this->prepareData($row);

        return Idea::updateOrCreate(
            ['id' => $row['id'] ?? null],
            $data
        );
    }

    /**
     * ربط العلاقات بعد حفظ النموذج
     */
    protected function afterModelSaved(Import $import, $model, array $row): void
    {
        // معالجة clients
        $clientCompanies = array_filter(array_map('trim', explode(',', $row['clients'] ?? '')));
        $clientIds = [];
        foreach ($clientCompanies as $company) {
            $client = Client::firstOrCreate(['company' => $company]);
            $clientIds[] = $client->id;
        }
        $model->clients()->sync($clientIds);

        // معالجة tags
        $tagNames = array_filter(array_map('trim', explode(',', $row['tags'] ?? '')));
        $tagIds = [];
        foreach ($tagNames as $tagName) {
            $tag = Tag::firstOrCreate(['name' => $tagName]);
            $tagIds[] = $tag->id;
        }
        $model->tags()->sync($tagIds);

        // معالجة locations
        $locationNames = array_filter(array_map('trim', explode(',', $row['locations'] ?? '')));
        $locationIds = [];
        foreach ($locationNames as $locationName) {
            $location = Location::firstOrCreate(['name' => $locationName]);
            $locationIds[] = $location->id;
        }
        $model->locations()->sync($locationIds);
    }

    /**
     * تحديد سجل موجود أو جديد لتحديث أو إنشاء
     */


    public function resolveRecord(): ?Idea
    {

        if (!empty($this->data['id'])) {
            return Idea::find($this->data['id']) ?? new Idea();
        }
        // return Idea::firstOrNew([
        //     // Update existing records, matching them by `$this->data['column_name']`
        //     'email' => $this->data['email'],
        // ]);

        return new Idea();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your idea import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
