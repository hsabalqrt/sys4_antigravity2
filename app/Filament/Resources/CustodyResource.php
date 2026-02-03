<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustodyResource\Pages;
use App\Filament\Resources\CustodyResource\RelationManagers;
use App\Models\Custody;
use App\Models\TagGroup;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * مورد Filament لإدارة العهد (Custodies).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف العهد المسلمة للمستخدمين.
 */
class CustodyResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = Custody::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'العهدة';
    protected static ?string $pluralLabel = 'العهدة';
    protected static ?string $label = 'عهدة';
    protected static ?string $slug = 'custodies';
    protected static ?string $navigationGroup = 'المستخدمون';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'عهدة';
    protected static ?string $pluralModelLabel = 'عهدات';
    protected static ?string $modelLabelPlural = 'العهدات';
    protected static ?string $pluralModelLabelPlural = 'العهدات';
    protected static ?string $navigationBadge = 'جديد';
    protected static ?string $navigationBadgeColor = 'success';
    protected static ?string $navigationSearch = 'true';
    protected static ?string $navigationSearchPlaceholder = 'ابحث عن عهدة...';
    protected static ?string $searchableAttribute = 'name';
    protected static ?string $searchableAttributePlural = 'name';
    protected static ?string $modelLabelSingular = 'عهدة';
    protected static ?string $modelLabelSingularPlural = 'العهدة';
    protected static ?string $modelLabelPluralSingular = 'العهدة';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل العهد.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required(),

                Select::make('user_id')
                    ->relationship('user', 'name') // يعتمد على وجود عمود name في users
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض العهد.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('رقم'),

                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('اسم المستلم'),

                Tables\Columns\TextColumn::make('addedBy.name')
                    ->label('أضيف بواسطة')
                    ->visible(false),

                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->label('آخر تعديل بواسطة')
                    ->badge()
                    ->separator(', ')
                    ->limit(30)
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإضافة'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخر تعديل'),
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
            'index' => Pages\ListCustodies::route('/'),
            'create' => Pages\CreateCustody::route('/create'),
            'edit' => Pages\EditCustody::route('/{record}/edit'),
        ];
    }
}
