<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientNeedResource\Pages;
use App\Filament\Resources\ClientNeedResource\RelationManagers;
use App\Models\ClientNeed;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Carbon\Carbon;

/**
 * مورد Filament لإدارة احتياجات العملاء (Client Needs).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف احتياجات العملاء،
 * مع ربطها بالوسوم والتصنيفات وتحديد درجة أهميتها.
 */
class ClientNeedResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = ClientNeed::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    /**
     * مجموعة التنقل التي ينتمي إليها المورد.
     *
     * @var string|null
     */
    protected static ?string $navigationGroup = 'المحتوى';

    /**
     * ترتيب المورد في قائمة التنقل.
     *
     * @var int|null
     */
    protected static ?int $navigationSort = 3;

    /**
     * اسم المورد في قائمة التنقل.
     *
     * @var string|null
     */
    protected static ?string $navigationLabel = 'احتياجات العملاء';

    /**
     * السمة المستخدمة كعنوان للسجل.
     *
     * @var string|null
     */
    protected static ?string $recordTitleAttribute = 'name';

    /**
     * الرابط الثابت (slug) للمورد.
     *
     * @var string|null
     */
    protected static ?string $slug = 'client-needs';

    /**
     * اسم النموذج المفرد.
     *
     * @var string|null
     */
    protected static ?string $modelLabel = 'احتياج عميل';

    /**
     * اسم النموذج بصيغة الجمع.
     *
     * @var string|null
     */
    protected static ?string $pluralModelLabel = 'احتياجات العملاء';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل احتياجات العملاء.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('البيانات الأساسية')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم الاحتياج')
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make('بيانات احتياج العميل')
                    ->schema([
                        CheckboxList::make('importance_level')
                            ->label('درجة الأهمية')
                            ->options([
                                'very_high' => 'عالية جداً',
                                'high' => 'عالية',
                                'medium' => 'متوسطة',
                                'low' => 'منخفضة',
                            ])
                            ->columns(4)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $importanceLevels = $state ?? [];
                                $currentTags = $get('tags') ?? [];
                                $categories = $get('categories') ?? [];
                                if (empty($importanceLevels)) {
                                    $set('tags', []);
                                    return;
                                }
                                $importanceMap = [
                                    'very_high' => 'veryhigh',
                                    'high' => 'high',
                                    'medium' => 'medium',
                                    'low' => 'low',
                                ];
                                $mappedLevels = array_map(fn($level) => $importanceMap[$level] ?? $level, $importanceLevels);
                                $filteredTags = array_filter($currentTags, function ($tagId) use ($mappedLevels, $categories) {
                                    $tag = \App\Models\Tag::with('categories')->find($tagId);
                                    if (!$tag) return false;
                                    $hasImportance = in_array($tag->importance, $mappedLevels);
                                    $hasCategory = empty($categories) || $tag->categories->pluck('id')->intersect($categories)->isNotEmpty();
                                    return $hasImportance && $hasCategory;
                                });
                                $set('tags', array_values($filteredTags));
                            }),

                        Select::make('tags')
                            ->label('التاقات')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->preload()
                            ->columns(2)
                            ->options(function ($get) {
                                $importanceLevels = $get('importance_level') ?? [];
                                $categories = $get('categories') ?? [];
                                if (empty($importanceLevels)) {
                                    return collect();
                                }
                                $importanceMap = [
                                    'very_high' => 'veryhigh',
                                    'high' => 'high',
                                    'medium' => 'medium',
                                    'low' => 'low',
                                ];
                                $mappedLevels = array_map(fn($level) => $importanceMap[$level] ?? $level, $importanceLevels);
                                $query = \App\Models\Tag::whereIn('importance', $mappedLevels);
                                if (!empty($categories)) {
                                    $query->whereHas('categories', fn($q) => $q->whereIn('id', $categories));
                                }
                                return $query->pluck('name', 'id');
                            })
                            ->reactive(),

                        Select::make('categories')
                            ->label('التصنيفات')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->preload()
                            ->columns(2)
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                $importanceLevels = $get('importance_level') ?? [];
                                $categories = $state ?? [];
                                $currentTags = $get('tags') ?? [];
                                if (empty($importanceLevels) || empty($categories)) {
                                    $set('tags', []);
                                    return;
                                }
                                $importanceMap = [
                                    'very_high' => 'veryhigh',
                                    'high' => 'high',
                                    'medium' => 'medium',
                                    'low' => 'low',
                                ];
                                $mappedLevels = array_map(fn($level) => $importanceMap[$level] ?? $level, $importanceLevels);
                                $filteredTags = array_filter($currentTags, function ($tagId) use ($mappedLevels, $categories) {
                                    $tag = \App\Models\Tag::with('categories')->find($tagId);
                                    if (!$tag) return false;
                                    $hasImportance = in_array($tag->importance, $mappedLevels);
                                    $hasCategory = $tag->categories->pluck('id')->intersect($categories)->isNotEmpty();
                                    return $hasImportance && $hasCategory;
                                });
                                $set('tags', array_values($filteredTags));
                            }),
                    ]),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض احتياجات العملاء.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم الاحتياج')
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('importance_level')
                    ->label('درجة الأهمية')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            $labels = [
                                'very_high' => 'عالية جداً',
                                'high' => 'عالية',
                                'medium' => 'متوسطة',
                                'low' => 'منخفضة',
                            ];
                            return collect($state)->map(fn($key) => $labels[$key] ?? $key)->implode(',');
                        }
                        return $state;
                    })
                    ->color(fn (string $state): string => match (trim($state)) {
                        'very_high' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        'low' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tags.name')
                    ->label('التاقات')
                    ->badge()
                    ->separator(',')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('categories.name')
                    ->label('التصنيفات')
                    ->badge()
                    ->separator(',')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('addedByUser.name')
                    ->label('أضيف بواسطة')
                    ->icon('heroicon-m-user')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updatedByUser.name')
                    ->label('عدّل بواسطة')
                    ->icon('heroicon-m-pencil-square')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime()
                    ->description(fn (ClientNeed $record): string => $record->created_at->diffForHumans())
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => Carbon::parse($state)->format('Y-m-d'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('تاريخ التحديث')
                    ->dateTime()
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('importance_level')
                    ->label('درجة الأهمية')
                    ->options([
                        'very_high' => 'عالية جداً',
                        'high' => 'عالية',
                        'medium' => 'متوسطة',
                        'low' => 'منخفضة',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        return $query->whereJsonContains('importance_level', $data['value']);
                    }),
                Tables\Filters\SelectFilter::make('tags')
                    ->label('التاقات')
                    ->relationship('tags', 'name')
                    ->preload()
                    ->multiple(),
                Tables\Filters\SelectFilter::make('categories')
                    ->label('التصنيفات')
                    ->relationship('categories', 'name')
                    ->preload()
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton(),
                Tables\Actions\DeleteAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListClientNeeds::route('/'),
            'create' => Pages\CreateClientNeed::route('/create'),
            'edit' => Pages\EditClientNeed::route('/{record}/edit'),
        ];
    }
}
