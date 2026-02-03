<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Tabs;
use Filament\Tables\Actions\Action;
use App\Filament\Resources\ClientResource\Pages\ViewClient;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\Split;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Get;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;

use Filament\Resources\Pages\Page;


/**
 * مورد Filament لإدارة العملاء (Clients).
 *
 * يوفر هذا المورد واجهة متكاملة لإنشاء وعرض وتعديل وحذف بيانات العملاء،
 * بما في ذلك معلوماتهم الأساسية، الاشتراكات، التقييمات، وغيرها.
 */
class ClientResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     *
     * @var string|null
     */
    protected static ?string $model = Client::class;

    /**
     * أيقونة التنقل للمورد.
     *
     * @var string|null
     */
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    /**
     * مجموعة التنقل التي ينتمي إليها المورد.
     *
     * @var string|null
     */
    protected static ?string $navigationGroup = 'CRM';

    /**
     * اسم المورد في قائمة التنقل.
     *
     * @var string|null
     */
    protected static ?string $navigationLabel = 'العملاء';

    /**
     * اسم النموذج بصيغة الجمع.
     *
     * @var string|null
     */
    protected static ?string $pluralModelLabel = 'العملاء';

    /**
     * الرابط الثابت (slug) للمورد.
     *
     * @var string|null
     */
    protected static ?string $slug = 'clients';

    /**
     * السمة المستخدمة كعنوان للسجل.
     *
     * @var string|null
     */
    protected static ?string $recordTitleAttribute = 'name';


    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل العملاء.
     *
     * @param  \Filament\Forms\Form  $form نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('البيانات الأساسية')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                // البيانات الأساسية
                                Section::make('البيانات الأساسية')
                                    ->schema([
                                        Forms\Components\TextInput::make('company')
                                            ->label('اسم الشركة')
                                            ->required(),
                                        Section::make('')
                                            ->schema([

                                                Forms\Components\TextInput::make('client_name')
                                                    ->label('اسم المالك او الشخص المتواصل معاه')
                                                    ->nullable(),
                                                Forms\Components\TextInput::make('address')
                                                    ->label('العنوان / المحافظة')
                                                    ->nullable(),
                                                Forms\Components\TextInput::make('contact_job')
                                                    ->label('وظيفته لدى الشركة')
                                                    ->nullable(),
                                                Forms\Components\TextInput::make('contact_number')
                                                    ->label('رقم الاتصال')
                                                    ->tel()
                                                    ->numeric()
                                                    ->maxLength(9)
                                                    // allow only 9 digits while typing
                                                    ->rule('regex:/^[0-9]{9}$/')
                                                    ->mask('999999999')
                                                    ->suffix('+967') // Yemen country code
                                                    ->nullable(),

                                            ])->columns(2),
                                        Forms\Components\Select::make('location_id')
                                            ->label('الموقع')
                                            ->relationship('location', 'name')
                                            ->preload() // لتحميل البيانات مسبقًا
                                            ->searchable()
                                            ->required(),
                                    ]),
                                Section::make('الباقة والاشتراك')
                                    ->schema([
                                        Forms\Components\TextInput::make('marketing_amount')
                                            ->label('المبلغ التسويقي')
                                            ->numeric()
                                            ->nullable(),
                                    ])->columns(2),


                                // العلاقات الأخرى
                                Forms\Components\Select::make('category_id')
                                    ->label('التصنيف')
                                    ->relationship('category', 'name')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $currentNeeds = $get('clientNeeds') ?? [];
                                        if (empty($state)) {
                                            $set('clientNeeds', []);
                                            return;
                                        }
                                        $filteredNeeds = array_filter($currentNeeds, function ($needId) use ($state) {
                                            $need = \App\Models\ClientNeed::with('categories')->find($needId);
                                            return $need && $need->categories->pluck('id')->contains($state);
                                        });
                                        $set('clientNeeds', array_values($filteredNeeds));
                                    })
                                    ->required(),
                                Forms\Components\Select::make('clientNeeds')
                                    ->label('احتياج العميل')
                                    ->multiple()
                                    ->preload()
                                    ->relationship('clientNeeds', 'name')
                                    ->options(function ($get) {
                                        $categoryId = $get('category_id');
                                        if (!$categoryId) {
                                            return collect();
                                        }
                                        return \App\Models\ClientNeed::whereHas('categories', fn($q) => $q->where('categories.id', $categoryId))->pluck('name', 'id');
                                    })
                                    ->reactive()
                                    ->nullable(),
                            ]),
                        Tabs\Tab::make('الاشعار والتوقيف')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Forms\Components\TextInput::make('suspension_days')
                                    ->label('مدة التوقيف (أيام)')
                                    ->numeric()
                                    ->nullable()
                                    ->live(),
                                // ->hidden(fn(Get $get): bool => $get('is_credit_allowed')),
                                Forms\Components\Toggle::make('is_credit_allowed')
                                    ->label('السقف الائتماني مسموح')
                                    ->default(false)
                                    ->live()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $set('suspension_days', null); // Set field to null
                                        }
                                    }),
                                Forms\Components\DateTimePicker::make('suspended_at')
                                    ->label('تاريخ التوقيف')
                                    ->nullable(),
                                // notified_at

                            ]),
                        Tabs\Tab::make('التقييم والكليشة')
                            ->icon('heroicon-o-star')
                            ->schema([
                                Forms\Components\TextInput::make('customer_rating_value')
                                    ->label('قيمة التقييم')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->nullable(),

                                Forms\Components\TextInput::make('change_cliche_threshold')
                                    ->label('كم عدد التصاميم لتغيير الكليشة')
                                    ->numeric()
                                    ->nullable(),
                            ]),
                    ])->columns(2),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض العملاء.
     *
     * @param  \Filament\Tables\Table  $table جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company')->label('الشركة')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('client_name')->visibleFrom('lg'),
                // Tables\Columns\TextColumn::make('client_name')->label('اسم العميل')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('location.name')->label('الموقع'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->badge(),
                Tables\Columns\TextColumn::make('customer_rating_value')->label('تقييم العميل')->sortable(),
                Tables\Columns\TextColumn::make('subscriptions_count')->label('عدد الاشتراكات')->counts('subscriptions'),
                Tables\Columns\IconColumn::make('status')->label('حالة العميل')->boolean(),
                Tables\Columns\TextColumn::make('mainSubscription.status')
                    ->label('حالة الاشتراك')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'expired' => 'danger',
                        'expiring_soon' => 'warning',
                        'active' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'expired' => 'منتهي',
                        'expiring_soon' => 'ينتهي قريباً',
                        'active' => 'نشط',
                        default => 'لا يوجد',
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('active_only')
                    ->label('العملاء النشطين')
                    ->query(fn(Builder $query): Builder => $query->where('status', true))
                    ->toggle(),

                Tables\Filters\Filter::make('suspended_only')
                    ->label('العملاء الموقوفين')
                    ->query(fn(Builder $query): Builder => $query->where('status', false))
                    ->toggle(),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->slideOver(),
                Tables\Actions\ViewAction::make(),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * يقوم بتعريف مكونات قائمة المعلومات (Infolist) لعرض تفاصيل العميل.
     *
     * @param  \Filament\Infolists\Infolist  $infolist قائمة معلومات Filament.
     * @return \Filament\Infolists\Infolist قائمة المعلومات المعرفة.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Tabs::make('ClientDetails')
                    ->columnSpanFull()
                    ->tabs([
                        \Filament\Infolists\Components\Tabs\Tab::make('ملف العميل')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Split::make([
                                    InfolistSection::make('معلومات الشركة')
                                        ->icon('heroicon-o-building-office')
                                        ->schema([
                                            TextEntry::make('company')
                                                ->label('اسم الشركة')
                                                ->size(TextEntry\TextEntrySize::Large)
                                                ->weight(FontWeight::Bold)
                                                ->color('primary'),
                                            TextEntry::make('category.name')
                                                ->label('التصنيف')
                                                ->badge()
                                                ->color('success'),
                                            TextEntry::make('location.name')
                                                ->label('الموقع')
                                                ->icon('heroicon-o-map-pin'),
                                        ])->grow(true),

                                    InfolistSection::make('حالة العميل')
                                        ->icon('heroicon-o-signal')
                                        ->schema([
                                            TextEntry::make('status')
                                                ->label('الحالة')
                                                ->badge()
                                                ->formatStateUsing(fn($state) => $state ? 'نشط' : 'موقف')
                                                ->color(fn($state) => $state ? 'success' : 'danger'),
                                            TextEntry::make('customer_rating_value')
                                                ->label('تقييم العميل')
                                                ->badge()
                                                ->color('warning')
                                                ->icon('heroicon-o-star'),
                                            TextEntry::make('subscriptions_count')
                                                ->label('عدد الاشتراكات')
                                                ->state(fn($record) => $record->subscriptions()->count())
                                                ->badge()
                                                ->color('info'),
                                        ])->grow(true),
                                ])->from('sm')->columnSpanFull()->grow(true),

                                InfolistSection::make('معلومات الاتصال')
                                    ->icon('heroicon-o-user-circle')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('client_name')
                                            ->label('اسم المسؤول')
                                            ->icon('heroicon-o-user'),
                                        TextEntry::make('contact_job')
                                            ->label('المنصب')
                                            ->icon('heroicon-o-briefcase'),
                                        TextEntry::make('contact_number')
                                            ->label('رقم الاتصال')
                                            ->icon('heroicon-o-phone')
                                            ->formatStateUsing(fn($state) => $state ? '+967 ' . $state : '-'),
                                        TextEntry::make('address')
                                            ->label('العنوان')
                                            ->icon('heroicon-o-map')
                                            ->columnSpanFull(),
                                    ]),

                                InfolistSection::make('الاحتياجات والتفضيلات')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('clientNeeds.name')
                                            ->label('احتياجات العميل')
                                            ->badge()
                                            ->separator(',')
                                            ->color('info'),
                                        TextEntry::make('marketing_amount')
                                            ->label('المبلغ التسويقي')
                                            ->money('YER')
                                            ->icon('heroicon-o-banknotes'),
                                        TextEntry::make('change_cliche_threshold')
                                            ->label('عتبة تغيير الكليشة')
                                            ->suffix(' تصميم'),
                                    ]),

                                InfolistSection::make('الإعدادات المالية')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('is_credit_allowed')
                                            ->label('السقف الائتماني')
                                            ->badge()
                                            ->formatStateUsing(fn($state) => $state ? 'مسموح' : 'غير مسموح')
                                            ->color(fn($state) => $state ? 'success' : 'danger'),
                                        TextEntry::make('suspension_days')
                                            ->label('مدة التوقيف')
                                            ->suffix(' يوم')
                                            ->placeholder('غير محدد'),
                                        TextEntry::make('suspended_at')
                                            ->label('تاريخ التوقيف')
                                            ->dateTime()
                                            ->placeholder('لم يتم التوقيف'),
                                    ]),

                                InfolistSection::make('معلومات إضافية')
                                    ->icon('heroicon-o-information-circle')
                                    ->columns(3)
                                    ->collapsible()
                                    ->schema([
                                        TextEntry::make('addedBy.name')
                                            ->label('أضيف بواسطة')
                                            ->icon('heroicon-o-user-plus'),
                                        TextEntry::make('updatedBy.name')
                                            ->label('آخر تحديث بواسطة')
                                            ->icon('heroicon-o-pencil-square')
                                            ->placeholder('لا يوجد'),
                                        TextEntry::make('created_at')
                                            ->label('تاريخ الإضافة')
                                            ->dateTime()
                                            ->icon('heroicon-o-clock'),
                                        TextEntry::make('updated_at')
                                            ->label('آخر تحديث')
                                            ->dateTime()
                                            ->since(),
                                    ]),
                            ]),
                        \Filament\Infolists\Components\Tabs\Tab::make('المالية والاشتراكات')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Split::make([
                                    InfolistSection::make('الملخص المالي')
                                        ->icon('heroicon-o-banknotes')
                                        ->schema([
                                            TextEntry::make('balance')
                                                ->label('الرصيد الحالي')
                                                ->money('YER')
                                                ->state(fn($record) => $record->balance)
                                                ->color(fn($state) => $state < 0 ? 'danger' : 'success')
                                                ->weight(FontWeight::Bold)
                                                ->size(TextEntry\TextEntrySize::Large),

                                            TextEntry::make('total_debits')
                                                ->label('إجمالي الرسوم (المدين)')
                                                ->money('YER')
                                                ->state(fn($record) => $record->transactions()->where('type', 'debit')->sum('amount'))
                                                ->color('info'),

                                            TextEntry::make('total_credits')
                                                ->label('إجمالي المدفوع (الدائن)')
                                                ->money('YER')
                                                ->state(fn($record) => $record->transactions()->where('type', 'credit')->sum('amount'))
                                                ->color('success'),
                                        ])->columns(3),
                                ])->columnSpanFull(),

                                Split::make([
                                    InfolistSection::make('تفاصيل الاشتراك الحالي')
                                        ->icon('heroicon-o-calendar')
                                        ->schema([
                                            TextEntry::make('mainSubscription.start_date')
                                                ->label('تاريخ فتح الاشتراك')
                                                ->date(format: 'Y-m-d')
                                                ->weight(FontWeight::Bold),

                                            TextEntry::make('mainSubscription.subscription_type')
                                                ->label('نوع الاشتراك'),

                                            TextEntry::make('mainSubscription.designs_count')
                                                ->label('عدد التصاميم'),

                                            TextEntry::make('mainSubscription.payment_amount')
                                                ->label('قيمة الاشتراك')
                                                ->money('YER')
                                                ->weight(FontWeight::Bold)
                                                ->color('secondary'),

                                            TextEntry::make('mainSubscription.status')
                                                ->label('حالة الاشتراك')
                                                ->badge()
                                                ->color(fn(string $state): string => match ($state) {
                                                    'expired' => 'danger',
                                                    'expiring_soon' => 'warning',
                                                    'active' => 'success',
                                                    default => 'gray',
                                                })
                                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                                    'expired' => 'منتهي',
                                                    'expiring_soon' => 'ينتهي قريباً',
                                                    'active' => 'نشط',
                                                    default => $state,
                                                }),
                                        ])->columns(3),
                                ])->columnSpanFull(),
                            ]),
                    ])
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
            RelationManagers\SubscriptionsRelationManager::class,
            RelationManagers\TransactionsRelationManager::class,
        ];
    }

    /**
     * يقوم بإرجاع صفحات (Pages) هذا المورد.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
            'view' => Pages\ViewClient::route('/{record}'),
        ];
    }
}
