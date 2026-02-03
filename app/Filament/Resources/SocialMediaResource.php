<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SocialMediaResource\Pages;
use App\Filament\Resources\SocialMediaResource\RelationManagers;
use App\Models\SocialMedia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * مورد Filament لإدارة وسائل التواصل الاجتماعي (Social Media).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف وسائل التواصل الاجتماعي.
 */
class SocialMediaResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = SocialMedia::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-share';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?string $navigationLabel = 'وسائل التواصل';
    protected static ?string $pluralModelLabel = 'وسائل التواصل';
    protected static ?string $modelLabel = 'وسيلة تواصل';
    protected static ?string $slug = 'social-media';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabelPlural = 'وسائل التواصل';
    protected static ?string $modelLabelSingular = 'وسيلة تواصل';
    protected static ?string $navigationBadge = 'جديد';
    protected static ?string $navigationBadgeColor = 'success';
    protected static ?string $navigationSearch = 'true';
    protected static ?string $navigationSearchPlaceholder = 'ابحث عن وسيلة تواصل...';
    protected static ?string $searchableAttribute = 'name';
    protected static ?string $searchableAttributePlural = 'name';
    protected static ?string $modelLabelSingularPlural = 'وسيلة تواصل';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل وسائل التواصل.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض وسائل التواصل.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('addedBy.name')->label('أضيف بواسطة')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('updatedBy.name')->label('آخر تعديل بواسطة')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإضافة')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->label('تاريخ التحديث')->dateTime()->toggleable(),
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
            'index' => Pages\ListSocialMedia::route('/'),
            'create' => Pages\CreateSocialMedia::route('/create'),
            'edit' => Pages\EditSocialMedia::route('/{record}/edit'),
        ];
    }
}
