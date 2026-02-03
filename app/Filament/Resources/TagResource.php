<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Filament\Resources\TagResource\RelationManagers;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Radio;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;

/**
 * مورد Filament لإدارة الوسوم (Tags).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف الوسوم،
 * مع خيارات متقدمة لتحديد الأهمية، التصنيفات، المواقع،
 * مجموعات الوسوم، والتكرار والجدولة.
 */
class TagResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = Tag::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'المحتوى';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'الوسوم';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $slug = 'tags';
    protected static ?string $modelLabel = 'وسم';
    protected static ?string $pluralModelLabel = 'وسوم';
    protected static ?string $modelLabelPlural = 'الوسوم';
    protected static ?string $modelLabelSingular = 'وسم';
    protected static ?string $modelLabelSingularPlural = 'وسم';
    protected static ?string $navigationBadge = 'جديد';
    protected static ?string $navigationBadgeColor = 'success';
    protected static ?string $navigationSearch = 'true';
    protected static ?string $navigationSearchPlaceholder = 'ابحث عن وسم...';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل الوسوم.
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
                            ->label('اسم الوسم')
                            ->required()
                            ->maxLength(100),
                    ]),
                Section::make('بيانات الوسم')
                    ->schema([
                        Select::make('importance')
                            ->label('درجة الأهمية')
                            ->options([
                                'veryhigh' => 'Very High',
                                'high' => 'High',
                                'medium' => 'Medium',
                                'low' => 'Low',
                            ])
                            ->default('medium'),

                        Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->label('التصنيفات'),

                        Select::make('locations')
                            ->relationship('locations', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->label('المواقع'),

                        Select::make('tag_group_id')
                            ->label('مجموعات الوسوم')
                            ->relationship('tagGroup', 'name')
                            ->preload()
                            ->required(),
                    ])->columns(2),

                Section::make('إعدادات الوسم')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true),

                        Toggle::make('is_auto_assigned')
                            ->label('تعيين تلقائي للعملاء')
                            ->default(true)
                            ->live()
                            ->helperText('عند التفعيل، سيتم تعيين الوسم تلقائياً للعملاء المطابقين للمواصفات.'),

                        Select::make('clients')
                            ->label('تحديد العملاء')
                            ->relationship('clients', 'company')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->visible(fn(Get $get): bool => ! $get('is_auto_assigned'))
                            ->required(fn(Get $get): bool => ! $get('is_auto_assigned'))
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('بيانات التكرار')
                    ->schema([
                        Toggle::make('is_repetition')
                            ->label('متكرر؟')
                            ->live(),

                        Radio::make('repetition')
                            ->label('نوع التكرار')
                            ->options([
                                'weekly' => 'Weekly',
                                // 'monthly' => 'Monthly',
                                'yearly' => 'Yearly',
                            ])
                            ->inline()
                            ->nullable()
                            ->live()
                            ->visible(fn(Get $get): bool => $get('is_repetition')),

                        TextInput::make('weekly_times')->numeric()
                            ->nullable()
                            ->label('عدد مرات التكرار الأسبوعي')
                            ->live()
                            ->helperText('يتم تحديد العدد تلقائياً بناءً على عدد أيام الإرسال المختارة.')
                            ->disabled() // Disabled because it depends on selected days
                            ->dehydrated() // We still probably want to save it if the system expects it
                            ->visible(fn(Get $get): bool => $get('is_repetition') && $get('repetition') === 'weekly'),
                        // TextInput::make('monthly_times')->numeric()->nullable(),
                        TextInput::make('yearly_times')
                            ->numeric()
                            ->nullable()
                            ->label('عدد مرات التكرار السنوي')
                            ->live()
                            ->visible(fn(Get $get): bool => $get('is_repetition') && $get('repetition') === 'yearly'),
                    ])->columns(2),
                Section::make('بيانات الإرسال')
                    ->description('يمكنك تحديد تاريخ الإرسال أو اليوم والوقت الأسبوعي')
                    ->schema([
                        Toggle::make('is_there_date_for_sending')
                            ->label('هل هناك تاريخ للإرسال؟')
                            ->live(),

                        Radio::make('sending_type')
                            ->label('نوع الإرسال')
                            ->options([
                                'weekly' => 'Weekly',
                                'yearly' => 'Yearly',
                            ])
                            ->default('weekly')
                            ->inline()
                            ->dehydrated(false)
                            ->live()
                            ->columnSpan(2)
                            ->afterStateHydrated(fn (Radio $component, $state, ?Tag $record) => $component->state($record?->date_for_sending_yearly ? 'yearly' : 'weekly'))
                            ->visible(fn(Get $get): bool => $get('is_there_date_for_sending')),

                        DatePicker::make('date_for_sending_yearly')->label('تاريخ الإرسال السنوي')
                            ->nullable()
                            ->live()
                            ->visible(fn(Get $get): bool => $get('is_there_date_for_sending') && $get('sending_type') === 'yearly'),

                        Select::make('weekly_day')
                            ->label('الأيام الأسبوعية')
                            ->options([
                                'Saturday' => 'Saturday',
                                'Sunday' => 'Sunday',
                                'Monday' => 'Monday',
                                'Tuesday' => 'Tuesday',
                                'Wednesday' => 'Wednesday',
                                'Thursday' => 'Thursday',
                                'Friday' => 'Friday',
                            ])
                            ->multiple() // Enable multiple selection
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            // Auto-calculate weekly_times when days change
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $set('weekly_times', count($state ?? []));
                            })
                            ->visible(fn(Get $get): bool => $get('is_there_date_for_sending') && $get('sending_type') === 'weekly'),

                        TimePicker::make('weekly_time')
                            ->label('الوقت الأسبوعي')
                            ->nullable()
                            ->live()
                            ->visible(fn(Get $get): bool => $get('is_there_date_for_sending') && $get('sending_type') === 'weekly'),
                        TimePicker::make('weekly_time_sm')
                            ->label('وقت ارسال في السوشيال ميديا')
                            ->nullable()
                            ->live()
                            ->visible(fn(Get $get): bool => $get('is_there_date_for_sending') && $get('sending_type') === 'weekly'),
                    ])->columns(3),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض الوسوم.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('importance')->badge(),
                TextColumn::make('categories.name')->label('Categories')->listWithLineBreaks(),
                TextColumn::make('locations.name')->label('Locations')->listWithLineBreaks(),
                TextColumn::make('tagGroup.name')->label('Tag Group'),
                TextColumn::make('weekly_day')
                    ->label('Days')
                    ->badge()
                    ->separator(','),
                TextColumn::make('weekly_day')->label('day'),
                TextColumn::make('weekly_time')->label('Time'),
                TextColumn::make('addedBy.name')->label('Added By'),
                TextColumn::make('updatedBy.name')->label('Updated By'),
                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة'),
                TextColumn::make('updated_at')
                    ->label('آخر تعديل'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
