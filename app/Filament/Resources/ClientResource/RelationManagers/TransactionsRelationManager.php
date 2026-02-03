<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Filament\Enums\SubscriptionType;
use App\Filament\Traits\PaymentFormHelpers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionsRelationManager extends RelationManager {
    use PaymentFormHelpers;
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'سجل المعاملات المالية';

    public function form(Form $form): Form {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\ToggleButtons::make('type')
                            ->label('نوع المعاملة')
                            ->options([
                                'credit' => 'سداد (إيداع)',
                                'debit' => 'رسوم (على العميل)',
                            ])
                            ->colors([
                                'credit' => 'info',
                                'debit' => 'warning',
                            ])
                            ->grouped()
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('subscription_id')
                            ->label('الاشتراك المراد سداده')
                            ->options(function (RelationManager $livewire) {
                                return $livewire->getOwnerRecord()->subscriptions()
                                    ->get()
                                    ->sortByDesc('created_at')
                                    ->mapWithKeys(function ($subscription) {
                                        $name = self::subscriptionTypeLabel($subscription->subscription_type);
                                        $amount = $subscription->payment_amount;
                                        $currency = $subscription->currency->currency;
                                        $endDate = $subscription->end_date->format('Y-m-d');
                                        return [$subscription->id => "{$name} - " . $endDate . " - " . $amount . " " . $currency];
                                    });
                            })
                            ->optionsLimit(5)
                            ->searchable()
                            ->placeholder('سداد عام (يخصم من إجمالي المديونية)')
                            ->visible(fn(Forms\Get $get) => $get('type') === 'credit')
                            ->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state, RelationManager $livewire) {
                                if ($state) {
                                    $sub = \App\Models\Subscription::with('currency')->find($state);
                                    if ($sub) {
                                        $set('currency_id', $sub->currency_id);
                                        $set('exchange_rate', 1); // Default to 1 when same as subscription
                                        static::calculateAmount($get, $set, $livewire);
                                    }
                                } else {
                                    $client = $livewire->getOwnerRecord();
                                    if ($client) {
                                        $set('currency_id', $client->currency_id);
                                        $set('exchange_rate', 1);
                                        static::calculateAmount($get, $set, $livewire);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('payment_method')
                            ->label('طريقة السداد')
                            ->options([
                                'monthly' => 'سداد شهر كامل (30 يوم)',
                                'specific_period' => 'سداد فترة محددة',
                                'additional_designs' => 'سداد تصاميم إضافية',
                            ])
                            ->visible(fn(Forms\Get $get) => $get('type') === 'credit' && $get('subscription_id') !== null)
                            ->required(fn(Forms\Get $get) => $get('type') === 'credit' && $get('subscription_id') !== null)
                            ->live(),

                        Forms\Components\DatePicker::make('transaction_date')
                            ->label('تاريخ العملية')
                            ->default(now())
                            ->required(),
                    ]),

                Forms\Components\Section::make('تفاصيل السداد والعملة')
                    ->schema([
                        Forms\Components\Select::make('currency_id')
                            ->label('عملة السداد')
                            ->relationship('currency', 'currency')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state, RelationManager $livewire) {
                                if (!$state) {
                                    return;
                                }
                                $subscriptionId = $get('subscription_id');
                                $targetCurrencyId = $subscriptionId
                                    ? \App\Models\Subscription::find($subscriptionId)?->currency_id
                                    : $livewire->getOwnerRecord()->currency_id;
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
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, RelationManager $livewire) {
                                $payId = $get('currency_id');
                                $subscriptionId = $get('subscription_id');
                                $targetId = $subscriptionId
                                    ? \App\Models\Subscription::find($subscriptionId)?->currency_id
                                    : $livewire->getOwnerRecord()->currency_id;
                                $amt = self::convertAmount($payId, $targetId, (float)$get('original_amount'), (float)$get('exchange_rate'));
                                $set('amount', $amt);
                            }),

                        Forms\Components\TextInput::make('original_amount')
                            ->label('المبلغ بالعملة المدفوعة')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, RelationManager $livewire) {
                                $payId = $get('currency_id');
                                $subscriptionId = $get('subscription_id');
                                $targetId = $subscriptionId
                                    ? \App\Models\Subscription::find($subscriptionId)?->currency_id
                                    : $livewire->getOwnerRecord()->currency_id;
                                $amt = self::convertAmount($payId, $targetId, (float)$get('original_amount'), (float)$get('exchange_rate'));
                                $set('amount', $amt);
                            }),

                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ الصافي (بعملة الاشتراك)')
                            ->numeric()
                            ->required()
                            ->reactive()
                            ->readOnly()
                            ->helperText(fn(Forms\Get $get, RelationManager $livewire) => self::conversionHint($get('currency_id'), ($get('subscription_id') ? \App\Models\Subscription::find($get('subscription_id'))?->currency_id : $livewire->getOwnerRecord()->currency_id))),

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
                    ])->columns(2),

                Forms\Components\TextInput::make('description')
                    ->label('البيان / الوصف')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                // Tables\Columns\TextColumn::make('id')
                //     ->label('رقم العملية')
                //     ->sortable(),
                Tables\Columns\TextColumn::make('type')
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

                Tables\Columns\TextColumn::make('subscription.subscription_type')
                    ->label('الاشتراك')
                    ->badge(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ الصافي')
                    ->color(fn($record) => $record->type === 'credit' ? 'success' : 'danger')
                    ->money(fn($record) => $record->subscription?->currency?->currency, locale: 'en'),

                Tables\Columns\TextColumn::make('original_amount')
                    ->label('المبلغ المدفوع')
                    ->money(fn($record) => $record->currency?->currency, locale: 'en'),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('التاريخ')
                    ->date(),

                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->limit(50),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->hidden(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('إضافة معاملة')
                    ->action(function (array $data, RelationManager $livewire) {
                        $client = $livewire->getOwnerRecord();
                        $subscription = isset($data['subscription_id']) ? \App\Models\Subscription::find($data['subscription_id']) : null;

                        // Handle Payment Logic
                        if ($data['type'] === 'credit') {
                            $method = $data['payment_method'] ?? null;

                            if ($method === 'monthly' && $subscription) {
                                // Extend by 30 days
                                $currentEndDate = $subscription->end_date ?? now();
                                if ($currentEndDate->isPast()) {
                                    $newEndDate = now()->addDays(30);
                                } else {
                                    $newEndDate = $currentEndDate->copy()->addDays(30);
                                }
                                $subscription->update(['end_date' => $newEndDate]);
                            } elseif ($method === 'specific_period' && $subscription) {
                                // Calculate daily rate (Payment Amount / 30)
                                $dailyRate = $subscription->payment_amount / 30;
                                if ($dailyRate > 0) {
                                    $daysToAdd = floor(($data['amount'] ?? 0) / $dailyRate);
                                    if ($daysToAdd > 0) {
                                        $currentEndDate = $subscription->end_date ?? now();
                                        if ($currentEndDate->isPast()) {
                                            $newEndDate = now()->addDays($daysToAdd);
                                        } else {
                                            $newEndDate = $currentEndDate->copy()->addDays($daysToAdd);
                                        }
                                        $subscription->update(['end_date' => $newEndDate]);
                                    }
                                }
                            } elseif ($method === 'additional_designs') {
                                // Update Client Balance
                                $client->increment('additional_designs_balance', 1);
                            }
                        }

                        return $livewire->getRelationship()->create($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function calculateAmount(Forms\Get $get, Forms\Set $set, RelationManager $livewire): void {
        $originalAmount = (float) $get('original_amount');
        $exchangeRate = (float) $get('exchange_rate');
        $paymentCurrencyId = $get('currency_id');
        $subscriptionId = $get('subscription_id');

        if (!$originalAmount || !$exchangeRate || !$paymentCurrencyId) {
            return;
        }

        // Target currency is subscription currency or client currency
        $targetCurrencyId = null;
        if ($subscriptionId) {
            $targetCurrencyId = \App\Models\Subscription::find($subscriptionId)?->currency_id;
        }

        if (!$targetCurrencyId) {
            $targetCurrencyId = $livewire->getOwnerRecord()->currency_id;
        }

        if ($paymentCurrencyId == $targetCurrencyId) {
            $set('amount', $originalAmount);
            return;
        }

        $paymentCur = \App\Models\Currency::find($paymentCurrencyId);
        $targetCur = \App\Models\Currency::find($targetCurrencyId);

        if (!$paymentCur || !$targetCur) {
            return;
        }

        // Logical Engine:
        // If payment currency value > target currency value (e.g., YER 530 > USD 1), it's weaker -> DIVIDE
        if ($paymentCur->value > $targetCur->value) {
            $set('amount', round($originalAmount / $exchangeRate, 2));
        } else {
            // Otherwise, it's stronger (or equal in value logic) -> MULTIPLY
            $set('amount', round($originalAmount * $exchangeRate, 2));
        }
    }

    protected static function getConversionLabel(Forms\Get $get, RelationManager $livewire): string {
        $paymentCurrencyId = $get('currency_id');
        $subscriptionId = $get('subscription_id');

        if (!$paymentCurrencyId) {
            return 'هذا المبلع هو ما سيتم قيده في الحساب';
        }

        $targetCurrencyId = $subscriptionId
            ? \App\Models\Subscription::find($subscriptionId)?->currency_id
            : $livewire->getOwnerRecord()->currency_id;

        if (!$targetCurrencyId) {
            return 'هذا المبلع هو ما سيتم قيده في الحساب';
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
}
