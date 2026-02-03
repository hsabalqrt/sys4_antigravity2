<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Filament\Resources\CurrencyResource\RelationManagers;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Grid;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * مورد Filament لإدارة العملات (Currencies).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف العملات المستخدمة في النظام.
 */
class CurrencyResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = Currency::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'الاعدادات';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'العملات';
    protected static ?string $recordTitleAttribute = 'currency_name';
    protected static ?string $slug = 'currencies';
    protected static ?string $modelLabel = 'عملة';
    protected static ?string $pluralModelLabel = 'عملات';
    protected static ?string $modelLabelPlural = 'العملات';
    protected static ?string $pluralModelLabelPlural = 'العملات';
    protected static ?string $navigationBadge = 'جديد';
    protected static ?string $navigationBadgeColor = 'success';
    protected static ?string $navigationSearch = 'true';
    protected static ?string $navigationSearchPlaceholder = 'ابحث عن عملة...';
    protected static ?string $searchableAttribute = 'currency_name';
    protected static ?string $searchableAttributePlural = 'currency_name';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل العملات.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('currency')
                        ->label('رمز العملة')
                        ->required()
                        ->maxLength(10),

                    TextInput::make('currency_name')
                        ->label('اسم العملة')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('value')
                        ->label('القيمة')
                        ->numeric()
                        ->required()
                        ->step(0.0001),
                ]),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض العملات.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('currency')->label('الرمز')->searchable(),
                TextColumn::make('currency_name')->label('اسم العملة')->searchable(),
                TextColumn::make('value')->label('القيمة')->numeric(),
                TextColumn::make('created_at')->label('تاريخ الإضافة')->dateTime(),
            ])
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
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
