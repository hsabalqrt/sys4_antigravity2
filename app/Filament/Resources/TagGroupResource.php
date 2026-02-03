<?php

namespace App\Filament\Resources;

use App\Filament\Exports\TagGroupExporter;
use App\Filament\Imports\TagGroupImporter;
use App\Filament\Resources\TagGroupResource\Pages;
use App\Filament\Resources\TagGroupResource\RelationManagers;
use App\Models\TagGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

/**
 * مورد Filament لإدارة مجموعات الوسوم (Tag Groups).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف مجموعات الوسوم.
 */
class TagGroupResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = TagGroup::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'مجموعات الوسوم';
    protected static ?string $pluralLabel = 'مجموعات الوسوم';
    protected static ?string $label = 'مجموعة وسم';
    protected static ?string $slug = 'tag-groups';
    protected static ?string $navigationGroup = 'المحتوى';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل مجموعات الوسوم.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('اسم المجموعة للوسوم')
                    ->required()
                    ->maxLength(100),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض مجموعات الوسوم.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('اسم المجموعة')->searchable(),
                TextColumn::make('addedByUser.name')->label('أُضيف بواسطة')->toggleable(),
                TextColumn::make('updatedByUser.name')->label('آخر تعديل بواسطة')->toggleable(),
                TextColumn::make('created_at')->label('تاريخ الإضافة')->toggleable(),
                TextColumn::make('updated_at')->label('آخر تعديل')->toggleable(),
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
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(TagGroupImporter::class)
                    ->options([
                        'authUserId' => Auth::id(),
                    ]),
                ExportAction::make()
                    ->exporter(TagGroupExporter::class),
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
            'index' => Pages\ListTagGroups::route('/'),
            'create' => Pages\CreateTagGroup::route('/create'),
            'edit' => Pages\EditTagGroup::route('/{record}/edit'),
        ];
    }
}
