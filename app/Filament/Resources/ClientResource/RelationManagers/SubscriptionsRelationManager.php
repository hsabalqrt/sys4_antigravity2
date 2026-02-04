<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Filament\Enums\SubscriptionType;
use App\Filament\Traits\PaymentFormHelpers;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\IconEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;

class SubscriptionsRelationManager extends RelationManager {
    use PaymentFormHelpers;
    protected static string $relationship = 'subscriptions';

    protected static ?string $label = 'الباقات';

    protected static ?string $pluralLabel = 'الباقات';

    protected static ?string $navigationLabel = 'الباقات';

    protected static ?string $pluralModelLabel = 'الباقات';

    protected static ?string $modelLabelPlural = 'الباقات';

    protected static ?string $modelLabel = 'الباقة';


    public static function updateEndDate(Forms\Set $set, Forms\Get $get) {
        $startDate = $get('start_date');
        $type = $get('subscription_type');

        if (!$startDate) {
            return;
        }

        $endDate = Subscription::calculateEndDateFrom($startDate, $type);

        $set('end_date', $endDate->format('Y-m-d'));
    }

    public function getSubscriptionFormSchema(?Subscription $record = null): array {
        return [
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Placeholder::make('debt_warning')
                        ->label('')
                        ->content(new \Illuminate\Support\HtmlString('<div class="p-4 bg-danger-500/10 text-danger-600 rounded-lg border border-danger-500 font-bold text-center">تنبيه: هذا العميل لديه مديونية مستحقة على هذا الاشتراك. يرجى التحصيل قبل التجديد.</div>'))
                        ->columnSpanFull()
                        ->visible(fn() => $record && $record->payment_status !== 'paid'),

                    Forms\Components\Fieldset::make('تفاصيل الاشتراك')
                        ->schema([

                            Forms\Components\Toggle::make('is_main')
                                ->label('اشتراك رئيسي')
                                ->afterStateUpdated(function ($state, callable $set, callable $get, $record) {
                                    if ($state && $record) {
                                        Subscription::where('client_id', $record->client_id)
                                            ->where('id', '!=', $record->id)
                                            ->update(['is_main' => false]);
                                    }
                                })
                                ->live()
                                ->default(fn($context) => $context === 'create' && $this->getOwnerRecord()->subscriptions()->count() === 0),

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
                                ->default('1')
                                ->required(),

                            Forms\Components\Select::make('tags')
                                ->label('التاقات')
                                ->multiple()
                                ->relationship('tags', 'name')
                                ->preload()
                                ->visible(fn(callable $get) => !$get('is_main')),
                        ])->columns(3),

                    Forms\Components\Fieldset::make('')
                        ->schema([
                            Forms\Components\Select::make('subscription_type')
                                ->label('نوع الاشتراك')
                                ->options(SubscriptionType::class)
                                ->default('monthly')
                                ->live()
                                ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => self::updateEndDate($set, $get))
                                ->selectablePlaceholder(false)
                                ->required(),

                            Forms\Components\DatePicker::make('start_date')
                                ->label('تاريخ البدء')
                                ->default(now())
                                ->live()
                                ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => self::updateEndDate($set, $get))
                                ->required(),

                            Forms\Components\DatePicker::make('end_date')
                                ->label('تاريخ الانتهاء')
                                ->default(fn(Forms\Get $get) => Subscription::calculateEndDateFrom($get('start_date'), $get('subscription_type')))
                                ->required()
                                ->dehydrated()
                                ->key('end_date'),
                        ])
                        ->columns(3),


                    Forms\Components\Fieldset::make('')
                        ->schema([

                            Forms\Components\Select::make('currency_id')
                                ->label('العملة')
                                ->relationship('currency', 'currency_name')
                                ->default(fn() => request()->query('currency_id'))
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    $set('paid_currency_id', $state);
                                    $set('exchange_rate', 1);
                                })
                                ->required(),





                            Forms\Components\TextInput::make('payment_amount')
                                ->label('المبلغ')
                                ->numeric()
                                ->prefix(fn(Forms\Get $get) => \App\Models\Currency::find($get('currency_id'))?->currency ?? 'N/A')
                                ->required(),

                            Forms\Components\Select::make('payment_type')
                                ->label('نوع الدفع')
                                ->options([
                                    'advance' => 'مقدم',
                                    'deferred' => 'آجل',
                                ])
                                ->default('advance')
                                ->live()
                                ->selectablePlaceholder(false)
                                ->required(),

                            Forms\Components\Toggle::make('is_paid_now')
                                ->label('تم استلام المبلغ الآن؟')
                                ->visible(fn(Forms\Get $get) => $get('payment_type') === 'advance')
                                ->live()
                                ->default(false),
                        ])->columns(4),

                    Forms\Components\Section::make('تفاصيل السداد')
                        ->schema([
                            Forms\Components\Select::make('paid_currency_id')
                                ->label('عملة السداد')
                                ->relationship('currency', 'currency')
                                ->required()
                                ->default(fn(Forms\Get $get) => $get('currency_id'))
                                ->live()
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                    if (!$state) {
                                        return;
                                    }
                                    $targetCurrencyId = $get('currency_id');
                                    $rate = self::computeExchangeRate($state, $targetCurrencyId);
                                    $set('exchange_rate', $rate);
                                    $amt = self::convertAmount($state, $targetCurrencyId, (float)$get('original_amount'), (float)$rate);
                                    $set('paid_amount', $amt);
                                    $set('amount', $amt);
                                }),

                            Forms\Components\TextInput::make('exchange_rate')
                                ->label('سعر الصرف')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                    $statePay = $get('paid_currency_id');
                                    $targetId = $get('currency_id');
                                    $amt = self::convertAmount($statePay, $targetId, (float)$get('original_amount'), (float)$get('exchange_rate'));
                                    $set('paid_amount', $amt);
                                    $set('amount', $amt);
                                }),

                            Forms\Components\TextInput::make('original_amount')
                                ->label('المبلغ بالعملة المدفوعة')
                                ->numeric()
                                ->required()
                                ->default(fn(Forms\Get $get) => $get('payment_amount'))
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                    $statePay = $get('paid_currency_id');
                                    $targetId = $get('currency_id');
                                    $amt = self::convertAmount($statePay, $targetId, (float)$get('original_amount'), (float)$get('exchange_rate'));
                                    $set('paid_amount', $amt);
                                    $set('amount', $amt);
                                }),

                            Forms\Components\TextInput::make('paid_amount')
                                ->label('المبلغ الصافي (بعملة الاشتراك)')
                                ->numeric()
                                ->required()
                                ->readOnly()
                                ->helperText(fn(Forms\Get $get) => self::conversionHint($get('paid_currency_id'), $get('currency_id'))),

                            Forms\Components\DatePicker::make('payment_date')
                                ->label('تاريخ السداد')
                                ->default(now())
                                ->required(),

                            Forms\Components\TextInput::make('payment_note')
                                ->label('ملاحظات السداد')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('payment_gateway')
                                ->label('طريقة السداد')
                                ->options([
                                    'cash' => 'كاش',
                                    'transfer' => 'حوالة',
                                ])
                                ->default('cash')
                                ->required()
                                ->live(),

                            Forms\Components\TextInput::make('transfer_number')
                                ->label('رقم الحوالة')
                                ->visible(fn(Forms\Get $get) => $get('payment_gateway') === 'transfer')
                                ->required(fn(Forms\Get $get) => $get('payment_gateway') === 'transfer'),
                        ])
                        ->visible(fn(Forms\Get $get) => $get('is_paid_now'))
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public function form(Form $form): Form {
        return $form->schema($this->getSubscriptionFormSchema())->columns(2);
    }

    public function table(Table $table): Table {
        return $table->modifyQueryUsing(fn($query) => $query->with(["currency"]))->modifyQueryUsing(fn($query) => $query->with(["currency"]))
            ->recordTitleAttribute('subscription_type')
            ->columns([
                Tables\Columns\IconColumn::make('is_main')
                    ->label('رئيسي')
                    ->boolean(),

                Tables\Columns\TextColumn::make('subscription_type')
                    ->label('النوع')
                    ->sortable(),

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

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('حالة السداد')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                    })
                    ->formatStateUsing(fn(string $state, Subscription $record): string => match ($state) {
                        'paid' => 'تم السداد',
                        'unpaid' => 'لم يتم السداد',
                        'partial' => 'متبقي عليه مديونية: ' . number_format(abs($record->balance), 2) . ($record->currency->currency ?? '$'),
                    }),

                Tables\Columns\TextColumn::make('designs_count')
                    ->label('التصاميم')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_amount')
                    ->label('المبلغ')
                    ->money(fn($record) => $record->currency->currency ?? 'USD', locale: 'en')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('تاريخ البدء')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('تاريخ الانتهاء')
                    ->date()
                    ->sortable(),
            ])->paginated([2, 5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(2)
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function (Subscription $record, array $data) {
                        if ($data['is_paid_now'] ?? false) {
                            $record->transactions()->create([
                                'client_id' => $record->client_id,
                                'amount' => $data['paid_amount'],
                                'original_amount' => $data['original_amount'],
                                'currency_id' => $data['paid_currency_id'],
                                'exchange_rate' => $data['exchange_rate'],
                                'type' => 'credit',
                                'payment_gateway' => $data['payment_gateway'] ?? 'cash',
                                'transfer_number' => $data['transfer_number'] ?? null,
                                'description' => $data['payment_note'] ?? (
                                    'سداد مقدم للاشتراك: ' . self::subscriptionTypeLabel($record->subscription_type)
                                ),
                                'transaction_date' => $data['payment_date'] ?? now(),
                            ]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\Action::make('record_payment')
                        ->label('تسديد المبلغ')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn(Subscription $record) => $record->payment_status !== 'paid')
                        ->modalHeading('تسجيل سداد مبلغ')
                        ->modalSubmitActionLabel('تسديد')
                        ->form([
                            Forms\Components\Hidden::make('currency_id')
                                ->default(fn(Subscription $record) => $record->currency_id),

                            Forms\Components\Select::make('paid_currency_id')
                                ->label('عملة السداد')
                                ->relationship('currency', 'currency')
                                ->required()
                                ->default(fn(Subscription $record) => $record->currency_id)
                                ->live()
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                    if (!$state) {
                                        return;
                                    }
                                    $targetCurrencyId = $get('currency_id');
                                    $rate = self::computeExchangeRate($state, $targetCurrencyId);
                                    $set('exchange_rate', $rate);
                                    $amt = self::convertAmount($state, $targetCurrencyId, (float)$get('original_amount'), (float)$rate);
                                    $set('amount', $amt);
                                }),

                            Forms\Components\TextInput::make('exchange_rate')
                                ->label('سعر الصرف')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                    $statePay = $get('paid_currency_id');
                                    $targetId = $get('currency_id');
                                    $amt = self::convertAmount($statePay, $targetId, (float)$get('original_amount'), (float)$get('exchange_rate'));
                                    $set('amount', $amt);
                                }),

                            Forms\Components\TextInput::make('original_amount')
                                ->label('المبلغ بالعملة المدفوعة')
                                ->numeric()
                                ->required()
                                ->default(fn(Subscription $record) => $record->payment_amount - $record->transactions()->where('type', 'credit')->sum('amount'))
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                    $statePay = $get('paid_currency_id');
                                    $targetId = $get('currency_id');
                                    $amt = self::convertAmount($statePay, $targetId, (float)$get('original_amount'), (float)$get('exchange_rate'));
                                    $set('amount', $amt);
                                }),

                            Forms\Components\TextInput::make('amount')
                                ->label('المبلغ الصافي (بعملة الاشتراك)')
                                ->numeric()
                                ->required()
                                ->readOnly()
                                ->helperText(fn(Forms\Get $get) => self::conversionHint($get('paid_currency_id'), $get('currency_id'))),

                            Forms\Components\DatePicker::make('payment_date')
                                ->label('تاريخ السداد')
                                ->default(now())
                                ->required(),

                            Forms\Components\TextInput::make('note')
                                ->label('ملاحظات')
                                ->columnSpanFull(),

                            Forms\Components\Select::make('payment_gateway')
                                ->label('طريقة السداد')
                                ->options([
                                    'cash' => 'كاش',
                                    'transfer' => 'حوالة',
                                ])
                                ->default('cash')
                                ->required()
                                ->live(),

                            Forms\Components\TextInput::make('transfer_number')
                                ->label('رقم الحوالة')
                                ->visible(fn(Forms\Get $get) => $get('payment_gateway') === 'transfer')
                                ->required(fn(Forms\Get $get) => $get('payment_gateway') === 'transfer'),
                        ])
                        ->action(function (array $data, Subscription $record): void {
                            $record->transactions()->create([
                                'client_id' => $record->client_id,
                                'amount' => $data['amount'],
                                'original_amount' => $data['original_amount'],
                                'currency_id' => $data['paid_currency_id'],
                                'exchange_rate' => $data['exchange_rate'],
                                'type' => 'credit',
                                'payment_gateway' => $data['payment_gateway'] ?? 'cash',
                                'transfer_number' => $data['transfer_number'] ?? null,
                                'description' => $data['note'] ?? (
                                    'تسديد مبلغ اشتراك: ' . self::subscriptionTypeLabel($record->subscription_type)
                                ),
                                'transaction_date' => $data['payment_date'],
                            ]);
                            Notification::make()
                                ->title('تم تسجيل السداد بنجاح')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('renew')
                        ->label('تجديد الاشتراك')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->hidden(fn(Subscription $record) => $record->status === 'active' && $record->days_remaining > 2)
                        ->mountUsing(fn(Forms\ComponentContainer $form, Subscription $record) => $form->fill([
                            'is_main' => $record->is_main,
                            'designs_count' => $record->designs_count,
                            'subscription_type' => $record->subscription_type,
                            'currency_id' => $record->currency_id,
                            'payment_amount' => $record->payment_amount,
                            'payment_type' => $record->payment_type,
                            'start_date' => now(),
                            'end_date' => Subscription::calculateEndDateFrom(now(), $record->subscription_type),
                        ]))
                        ->form(fn(Subscription $record) => $this->getSubscriptionFormSchema($record))
                        ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge)
                        ->action(function (array $data, Subscription $record): void {
                            $tags = $data['tags'] ?? [];
                            unset($data['tags']);

                            $isPaidNow = $data['is_paid_now'] ?? false;
                            unset($data['is_paid_now']);

                            $paidAmount = $data['paid_amount'] ?? null;
                            $paymentDate = $data['payment_date'] ?? null;
                            $paymentNote = $data['payment_note'] ?? null;
                            unset($data['paid_amount'], $data['payment_date'], $data['payment_note']);

                            $newSubscription = $record->client->subscriptions()->create($data);

                            if (!empty($tags)) {
                                $newSubscription->tags()->sync($tags);
                            }

                            if ($isPaidNow) {
                                $newSubscription->transactions()->create([
                                    'client_id' => $newSubscription->client_id,
                                    'amount' => $paidAmount,
                                    'original_amount' => $data['original_amount'] ?? $paidAmount,
                                    'currency_id' => $data['paid_currency_id'] ?? $newSubscription->currency_id,
                                    'exchange_rate' => $data['exchange_rate'] ?? 1,
                                    'type' => 'credit',
                                    'payment_gateway' => $data['payment_gateway'] ?? 'cash',
                                    'transfer_number' => $data['transfer_number'] ?? null,
                                    'description' => $paymentNote ?? ('سداد تجديد اشتراك: ' . self::subscriptionTypeLabel($newSubscription->subscription_type)),
                                    'transaction_date' => $paymentDate ?? now(),
                                ]);
                            }

                            Notification::make()
                                ->title('تم تجديد الاشتراك بنجاح')
                                ->success()
                                ->send();
                        })
                        ->modalHeading('تجديد الاشتراك')
                        ->modalSubmitActionLabel('تجديد الآن'),
                    // ->modalDescription(fn(Subscription $record) => $record->payment_status !== 'paid' ? 'تنبيه: هذا العميل لديه مديونية مستحقة. يرجى تحصيل المبالغ قبل التجديد.' : null),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\Action::make('change_status')
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


    /**
     * يقوم بتعريف مكونات قائمة المعلومات (Infolist) لعرض تفاصيل العميل.
     *
     * @param  \Filament\Infolists\Infolist  $infolist قائمة معلومات Filament.
     * @return \Filament\Infolists\Infolist قائمة المعلومات المعرفة.
     */
    public function infolist(Infolist $infolist): Infolist {
        return $infolist
            ->schema([
                Fieldset::make('تفاصيل الاشتراك')
                    ->schema([

                        IconEntry::make('is_main')
                            ->label('اشتراك أساسي')
                            ->boolean(),
                        TextEntry::make('designs_count')
                            ->label('عدد التصاميم'),
                        TextEntry::make('subscription_type')
                            ->label('نوع الاشتراك')
                            ->badge(),
                        TextEntry::make('status')
                            ->label('الحالة')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'active' => 'success',
                                'expired' => 'danger',
                                default => 'gray',
                            }),
                    ])->columns(4),

                Fieldset::make('تفاصيل الدفع')
                    ->schema([

                        TextEntry::make('payment_amount')
                            ->label('قيمة الاشتراك')
                            ->extraEntryWrapperAttributes(['class' => 'entry-locked'])
                            ->money(fn($record) => $record->currency?->code ?? 'USD', locale: 'en'),
                        TextEntry::make('payment_type')
                            ->label('طريقة الدفع')
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'deferred' => 'مؤخر',
                                'advance' => 'مقدم',
                                default => 'غير محدد',
                            }),
                        TextEntry::make('start_date')
                            ->label('تاريخ البدء')
                            ->date(),
                        TextEntry::make('end_date')
                            ->label('تاريخ الانتهاء')
                            ->date(),
                    ]),
                Fieldset::make('المعاملات المالية')
                    ->schema([
                        RepeatableEntry::make('transactions')
                            ->label('')
                            ->schema([
                                TextEntry::make('type')
                                    ->label('النوع')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'credit' => 'success',
                                        'debit' => 'danger',
                                    })
                                    ->formatStateUsing(fn(string $state): string => match ($state) {
                                        'credit' => 'سداد',
                                        'debit' => 'رسوم',
                                    }),
                                TextEntry::make('amount')
                                    ->label('المبلغ الصافي')
                                    ->money(fn($record) => $record->subscription->currency->currency ?? 'USD'),
                                TextEntry::make('original_amount')
                                    ->label('المبلغ المدفوع')
                                    ->formatStateUsing(fn($record) => $record->original_amount . ' ' . ($record->currency?->currency ?? '')),
                                TextEntry::make('transaction_date')
                                    ->label('التاريخ')
                                    ->date(),
                                TextEntry::make('description')
                                    ->label('الوصف'),
                            ])
                            ->columns(5)
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

            ]);
    }
    protected static function calculateAmount(Forms\Get $get, Forms\Set $set): void {
        $originalAmount = (float) $get('original_amount');
        $exchangeRate = (float) $get('exchange_rate');
        $paymentCurrencyId = $get('paid_currency_id');
        $targetCurrencyId = $get('currency_id');

        if (!$originalAmount || !$exchangeRate || !$paymentCurrencyId || !$targetCurrencyId) {
            return;
        }

        if ($paymentCurrencyId == $targetCurrencyId) {
            $set('paid_amount', $originalAmount);
            $set('amount', $originalAmount); // For record_payment modal
            return;
        }

        $paymentCur = \App\Models\Currency::find($paymentCurrencyId);
        $targetCur = \App\Models\Currency::find($targetCurrencyId);

        if (!$paymentCur || !$targetCur) {
            return;
        }

        $calculatedAmount = 0;
        if ($paymentCur->value > $targetCur->value) {
            $calculatedAmount = round($originalAmount / $exchangeRate, 2);
        } else {
            $calculatedAmount = round($originalAmount * $exchangeRate, 2);
        }

        $set('paid_amount', $calculatedAmount);
        $set('amount', $calculatedAmount); // For record_payment modal
    }

    protected static function getConversionLabel(Forms\Get $get): string {
        $paymentCurrencyId = $get('paid_currency_id');
        $targetCurrencyId = $get('currency_id');

        if (!$paymentCurrencyId || !$targetCurrencyId) {
            return 'هذا المبلغ هو ما سيتم قيده في الحساب';
        }

        if ($paymentCurrencyId == $targetCurrencyId) {
            return 'نفس العملة، لا يوجد تحويل';
        }

        $paymentCur = \App\Models\Currency::find($paymentCurrencyId);
        $targetCur = \App\Models\Currency::find($targetCurrencyId);

        if (!$paymentCur || !$targetCur) {
            return '';
        }

        if ($paymentCur->value > $targetCur->value) {
            return "سيتم (تقسيم) المبلغ على سعر الصرف للتحويل من {$paymentCur->currency} إلى {$targetCur->currency}";
        }

        return "سيتم (ضرب) المبلغ في سعر الصرف للتحويل من {$paymentCur->currency} إلى {$targetCur->currency}";
    }

    protected static function subscriptionTypeLabel($type): string {
        if (is_object($type) && method_exists($type, 'getLabel')) {
            return (string) $type->getLabel();
        }
        if (is_string($type) || is_int($type)) {
            $from = \App\Filament\Enums\SubscriptionType::tryFrom($type);
            return $from?->getLabel() ?? (string) $type;
        }
        return (string) $type;
    }
}
