<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DesignerResource\Pages;
use App\Filament\Resources\DesignerResource\RelationManagers;
use App\Models\Designer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;


/**
 * مورد Filament لإدارة المصممين (Designers).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف بيانات المصممين،
 * بما في ذلك معلوماتهم الأساسية، تفاصيل العمل، والتصنيفات المرتبطة بهم.
 */
class DesignerResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = Designer::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'المستخدمون';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'المصممين';
    protected static ?string $recordTitleAttribute = 'user.name';
    protected static ?string $slug = 'designers';
    protected static ?string $modelLabel = 'مصمم';
    protected static ?string $pluralModelLabel = 'مصممين';
    protected static ?string $modelLabelPlural = 'المصممين';
    protected static ?string $pluralModelLabelPlural = 'المصممين';
    protected static ?string $navigationBadge = 'جديد';
    protected static ?string $navigationBadgeColor = 'success';
    protected static ?string $navigationSearch = 'true';
    protected static ?string $navigationSearchPlaceholder = 'ابحث عن مصمم...';
    protected static ?string $searchableAttribute = 'user.name';
    protected static ?string $searchableAttributePlural = 'user.name';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل المصممين.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('البيانات الأساسية')->schema([
                    Select::make('user_id')
                        ->relationship('user', 'name', modifyQueryUsing: function (Builder $query, $record) {
                            $query->whereNotIn('id', Designer::query()
                                ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                ->pluck('user_id')
                                ->toArray());
                        })
                        ->searchable()
                        ->preload()
                        ->label('المستخدم')
                        ->required(),

                    Select::make('categories')
                        ->multiple()
                        ->relationship('categories', 'name')
                        ->preload()
                        ->label('التصنيفات')
                        ->required(),
                ])->columns(2),

                Section::make('تفاصيل العمل')->schema([
                    TextInput::make('min_capacity')->numeric()->label('الحد الأدنى لعدد التصاميم'),
                    TextInput::make('max_capacity')->numeric()->label('الحد الأعلى لعدد التصاميم'),
                    TextInput::make('rate')->numeric()->label('التقييم'),
                    TextInput::make('shift_hours')->numeric()->label('ساعات الدوام'),
                    TextInput::make('discipline_score')->numeric()->label('الانضباط'),
                    TextInput::make('amount_of_designs')->numeric()->label('عدد التصاميم'),
                    TextInput::make('freepik_account')->label('حساب فري بيك'),
                    TextInput::make('pc_number')->label('رقم الجهاز'),
                ])->columns(2),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض المصممين.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('المستخدم')->searchable(),

                Tables\Columns\TextColumn::make('min_capacity')->label('الحد الأدنى للسعة'),
                Tables\Columns\TextColumn::make('max_capacity')->label('الحد الأقصى للسعة'),
                Tables\Columns\TextColumn::make('rate')->label('التقييم'),
                Tables\Columns\TextColumn::make('shift_hours')->label('عدد ساعات الدوام'),
                Tables\Columns\TextColumn::make('discipline_score')->label('تقييم الانضباط'),
                Tables\Columns\TextColumn::make('amount_of_designs')->label('عدد التصاميم'),

                Tables\Columns\TextColumn::make('freepik_account')->label('حساب فريبيك')->wrap(),
                Tables\Columns\TextColumn::make('pc_number')->label('رقم الجهاز'),

                Tables\Columns\TextColumn::make('categories.name')
                    ->label('التصنيفات')
                    ->badge()
                    ->separator(', ')
                    ->limit(30),
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
            'index' => Pages\ListDesigners::route('/'),
            'create' => Pages\CreateDesigner::route('/create'),
            // 'edit' => Pages\EditDesigner::route('/{record}/edit'),
        ];
    }
}
