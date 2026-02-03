<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource\RelationManagers;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * مورد Filament لإدارة المواقع (Locations).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف المواقع الجغرافية.
 */
class LocationResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = Location::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'المواقع';
    protected static ?string $pluralLabel = 'المواقع';
    protected static ?string $label = 'موقع';
    protected static ?string $slug = 'locations';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'موقع';
    protected static ?string $pluralModelLabel = 'المواقع';
    protected static ?string $modelLabelPlural = 'المواقع';
    protected static ?string $modelLabelSingular = 'موقع';
    protected static ?string $modelLabelSingularPlural = 'الموقع';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل المواقع.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('اسم الموقع')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض المواقع.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('اسم الموقع'),
                Tables\Columns\TextColumn::make('addedBy.name')->label('أضيف بواسطة')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('updatedBy.name')->label('آخر تعديل بواسطة')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإضافة')->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')->label('تاريخ التحديث')->dateTime(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * يقوم بإرجاع مديري العلاقات (Relation Managers) لهذا المورد.
     *
     * @return array
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * يقوم بإرجاع صفحات (Pages) لهذا المورد.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
