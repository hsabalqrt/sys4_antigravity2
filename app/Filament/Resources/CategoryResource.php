<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * مورد Filament لإدارة الفئات (Categories).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف الفئات في لوحة التحكم.
 */
class CategoryResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = Category::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'المحتوى';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'التصنيفات';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $slug = 'categories';
    protected static ?string $modelLabel = 'تصنيف';
    protected static ?string $pluralModelLabel = 'تصنيفات';
    protected static ?string $modelLabelPlural = 'التصنيفات';
    protected static ?string $pluralModelLabelPlural = 'التصنيفات';
    protected static ?string $navigationBadge = 'جديد';
    protected static ?string $navigationBadgeColor = 'success';
    protected static ?string $navigationSearch = 'true';
    protected static ?string $navigationSearchPlaceholder = 'ابحث عن تصنيف...';
    protected static ?string $searchableAttribute = 'name';
    protected static ?string $searchableAttributePlural = 'name';


    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل الفئات.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('اسم التصنيف')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض الفئات.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable()->label('اسم التصنيف'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('تاريخ التحديث')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('addedByUser.name')
                    ->label('تمت الإضافة بواسطة')
                    ->sortable()
                    ->searchable()
                    ->placeholder('غير معروف')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updatedByUser.name')
                    ->label('تم التحديث بواسطة')
                    ->sortable()
                    ->searchable()
                    ->placeholder('غير معروف')
                    ->toggleable(),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
