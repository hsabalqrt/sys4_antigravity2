<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use App\Models\Currency;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function updateEndDate(Forms\Set $set, Forms\Get $get)
    {
        $startDate = $get('start_date');
        $type = $get('subscription_type');

        if (!$startDate) {
            return;
        }

        $endDate = Subscription::calculateEndDateFrom($startDate, $type);

        $set('end_date', $endDate->format('Y-m-d'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('client_id')
                    ->label('العميل')
                    ->relationship('client', 'company')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->company ?? $record->client_name ?? 'N/A')
                    ->searchable()
                    ->preload()
                    ->default(fn() => request()->query('client_id'))
                    ->required(),

                Forms\Components\Toggle::make('is_main')
                    ->label('اشتراك رئيسي')
                    ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                        if ($state && $record) {
                            // Unset is_main for other subscriptions of this client
                            Subscription::where('client_id', $record->client_id)
                                ->where('id', '!=', $record->id)
                                ->update(['is_main' => false]);
                        }
                    })
                    ->live()
                    ->default(true),

                Forms\Components\Select::make('designs_count')
                    ->label('عدد التصاميم')
                    ->options([
                        '1' => '1',
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                        '5' => '5',
                        '6' => '6',
                        '7' => '7',
                    ])
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->default(fn() => request()->query('designs_count') ?? '1')
                    ->required(),

                Forms\Components\DatePicker::make('start_date')
                    ->label('تاريخ البدء')
                    ->default(now())
                    ->live()
                    ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => self::updateEndDate($set, $get))
                    ->required(),


                Forms\Components\Select::make('subscription_type')
                    ->label('نوع الاشتراك')
                    ->options([
                        'weekly' => 'أسبوعي',
                        'monthly' => 'شهري',
                        'yearly' => 'سنوي',
                    ])
                    ->default('monthly')
                    ->live()
                    ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => self::updateEndDate($set, $get))
                    ->required(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('تاريخ الانتهاء')
                    ->default(fn(Forms\Get $get) => Subscription::calculateEndDateFrom($get('start_date'), $get('subscription_type')))
                    ->required()
                    ->dehydrated()
                    ->key('end_date'),

                Forms\Components\Select::make('currency_id')
                    ->label('العملة')
                    ->relationship('currency', 'currency_name')
                    ->default(fn() => request()->query('currency_id'))
                    ->live()
                    ->required(),

                Forms\Components\TextInput::make('payment_amount')
                    ->label('المبلغ')
                    ->numeric()
                    ->prefix(fn(Forms\Get $get) => Currency::find($get('currency_id'))?->currency ?? 'N/A')
                    ->default(fn() => request()->query('payment_amount'))
                    ->required(),



                Forms\Components\Select::make('tags')
                    ->label('التاقات')
                    ->multiple()
                    ->relationship('tags', 'name')
                    ->preload()
                    ->visible(fn(callable $get) => !$get('is_main')),

                Forms\Components\Select::make('payment_type')
                    ->label('نوع الدفع')
                    ->options([
                        'advance' => 'مقدم',
                        'deferred' => 'آجل',
                    ])
                    ->default('advance')
                    ->live()
                    ->required(),

                Forms\Components\Toggle::make('is_paid_now')
                    ->label('تم استلام المبلغ الآن؟')
                    ->visible(fn(Forms\Get $get) => $get('payment_type') === 'advance')
                    ->live()
                    ->default(false),

                Forms\Components\Section::make('تفاصيل السداد')
                    ->schema([
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('المبلغ المدفوع')
                            ->numeric()
                            ->default(fn(Forms\Get $get) => $get('payment_amount'))
                            ->required(),
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('تاريخ السداد')
                            ->default(now())
                            ->required(),
                        Forms\Components\TextInput::make('payment_note')
                            ->label('ملاحظات السداد')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(Forms\Get $get) => $get('is_paid_now'))
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_main')
                    ->label('رئيسي')
                    ->boolean(),

                Tables\Columns\TextColumn::make('subscription_type')
                    ->label('النوع')
                    ->sortable(),

                // payment_type
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('نوع الدفع')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'advance' => 'مقدم',
                        'deferred' => 'آجل',
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'advance' => 'success',
                        'deferred' => 'warning',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'expired' => 'danger',
                        'expiring_soon' => 'warning',
                        'active' => 'success',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'expired' => 'منتهي',
                        'expiring_soon' => 'ينتهي قريباً',
                        'active' => 'نشط',
                    }),


                Tables\Columns\TextColumn::make('designs_count')
                    ->label('التصاميم')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_amount')
                    ->label('المبلغ')
                    ->money(fn($record) => $record->currency->symbol ?? 'USD', locale: 'en')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('تاريخ البدء')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('تاريخ الانتهاء')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->label('تعديل'),
                    Tables\Actions\Action::make('renew')
                        ->label('تجديد الاشتراك')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->hidden(fn(Subscription $record) => $record->status === 'active' && $record->days_remaining > 2) // Hide if still active and not near expiry
                        ->disabled(
                            fn(Subscription $record) =>
                            $record->payment_type === 'deferred' && $record->balance < 0 // Block postpaid renewal if debt exists
                        )
                        ->requiresConfirmation(fn(Subscription $record) => $record->payment_type === 'deferred' && $record->balance < 0)
                        ->modalHeading('تنبيه مديونية')
                        ->modalDescription('هذا العميل لديه مديونية مستحقة. يرجى تحصيل المبالغ قبل التجديد.')
                        ->action(fn(Subscription $record) => redirect(SubscriptionResource::getUrl('create', [
                            'client_id' => $record->client_id,
                            'designs_count' => $record->designs_count,
                            'subscription_type' => $record->subscription_type,
                            'payment_amount' => $record->payment_amount,
                            'currency_id' => $record->currency_id,
                        ]))),
                    Tables\Actions\DeleteAction::make()
                        ->label('حذف'),
                    Tables\Actions\Action::make('change_status')
                        // hide if status is expired
                        ->hidden(fn(Subscription $record) => $record->status === 'expired')
                        ->label('تغيير الحالة')
                        ->icon('heroicon-o-pencil')
                        ->color('warning')
                        ->action(function (Subscription $record) {
                            $record->update([
                                'status' => $record->status === 'active' ? 'expired' : 'active',
                            ]);
                        }),
                ]),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
