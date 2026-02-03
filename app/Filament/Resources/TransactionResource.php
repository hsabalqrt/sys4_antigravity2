<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Client;
use App\Models\Subscription;
use App\Models\Currency;
use App\Filament\Traits\PaymentFormHelpers;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource {
    use PaymentFormHelpers;
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Sales';

    public static function form(Form $form): Form {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('العميل')
                            ->relationship('client', 'company')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('subscription_id', null);
                            }),

                        Forms\Components\Select::make('subscription_id')
                            ->label('الاشتراك')
                            ->options(function (Get $get) {
                                $clientId = $get('client_id');
                                if (!$clientId) {
                                    return [];
                                }
                                return Subscription::where('client_id', $clientId)
                                    ->get()
                                    ->mapWithKeys(function ($sub) {
                                        $label = self::subscriptionTypeLabel($sub->subscription_type);
                                        return [$sub->id => "اشتراك #{$sub->id} - {$label} ({$sub->currency->currency})"];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if (!$state) {
                                    return;
                                }
                                $sub = Subscription::with('currency')->find($state);
                                if ($sub) {
                                    // Align paid currency to subscription currency; rate = 1
                                    $set('currency_id', $sub->currency_id);
                                    $set('exchange_rate', 1);
                                    $set('amount', self::convertAmount($sub->currency_id, $sub->currency_id, (float)$get('original_amount'), 1));
                                }
                            }),

                        Forms\Components\Select::make('type')
                            ->label('نوع المعاملة')
                            ->options([
                                'credit' => 'دفع (إيداع)',
                                'debit' => 'خصم (رسوم)',
                            ])
                            ->required()
                            ->default('credit'),

                        Forms\Components\DatePicker::make('transaction_date')
                            ->label('تاريخ المعاملة')
                            ->default(now())
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('تفاصيل المبلغ والعملة')
                    ->schema([
                        Forms\Components\Select::make('currency_id')
                            ->label('عملة السداد (العملة التي دفع بها العميل)')
                            ->relationship('currency', 'currency')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if (!$state) {
                                    return;
                                }
                                $targetId = self::resolveTargetCurrencyId($get('subscription_id'), $get('client_id'));
                                $rate = self::computeExchangeRate($state, $targetId);
                                $set('exchange_rate', $rate);
                                $set('amount', self::convertAmount($state, $targetId, (float)$get('original_amount'), (float)$rate));
                            }),

                        Forms\Components\TextInput::make('exchange_rate')
                            ->label('سعر الصرف')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $targetId = self::resolveTargetCurrencyId($get('subscription_id'), $get('client_id'));
                                $set('amount', self::convertAmount($get('currency_id'), $targetId, (float)$get('original_amount'), (float)$get('exchange_rate')));
                            }),

                        Forms\Components\TextInput::make('original_amount')
                            ->label('المبلغ بالعملة المدفوعة')
                            ->numeric()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $targetId = self::resolveTargetCurrencyId($get('subscription_id'), $get('client_id'));
                                $set('amount', self::convertAmount($get('currency_id'), $targetId, (float)$get('original_amount'), (float)$get('exchange_rate')));
                            }),

                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ الصافي (بعملة الاشتراك/العميل)')
                            ->helperText(fn(Get $get) => self::conversionHint($get('currency_id'), self::resolveTargetCurrencyId($get('subscription_id'), $get('client_id'))))
                            ->numeric()
                            ->required()
                            ->readOnly(),
                    ])->columns(2),

                Forms\Components\Textarea::make('description')
                    ->label('الوصف')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'credit' => 'دفع',
                        'debit' => 'خصم',
                    }),

                Tables\Columns\TextColumn::make('subscription.subscription_type')
                    ->label('الاشتراك')
                    ->badge(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ الصافي')
                    ->color(fn($record) => $record->type === 'credit' ? 'success' : 'danger')
                    ->money(fn($record) => $record->subscription?->currency?->currency ?? 'USD', locale: 'en')
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_amount')
                    ->label('المبلغ المدفوع')
                    ->money(fn($record) => $record->currency?->currency ?? 'USD', locale: 'en'),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->limit(50),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'company')
                    ->label('العميل')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'credit' => 'دفع',
                        'debit' => 'خصم',
                    ])
                    ->label('النوع'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('عرض'),
                    Tables\Actions\EditAction::make()
                        ->label('تعديل'),
                    Tables\Actions\DeleteAction::make()
                        ->label('حذف'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function calculateAmount(Get $get, Set $set): void {
        $originalAmount = (float) $get('original_amount');
        $exchangeRate = (float) $get('exchange_rate');
        $paymentCurrencyId = $get('currency_id');
        $subscriptionId = $get('subscription_id');
        $clientId = $get('client_id');

        if (!$originalAmount || !$exchangeRate || !$paymentCurrencyId) {
            return;
        }

        $targetCurrencyId = null;
        if ($subscriptionId) {
            $targetCurrencyId = Subscription::find($subscriptionId)?->currency_id;
        }

        if (!$targetCurrencyId && $clientId) {
            $targetCurrencyId = Client::find($clientId)?->currency_id;
        }

        if (!$targetCurrencyId) {
            return;
        }

        if ($paymentCurrencyId == $targetCurrencyId) {
            $set('amount', $originalAmount);
            return;
        }

        $paymentCur = Currency::find($paymentCurrencyId);
        $targetCur = Currency::find($targetCurrencyId);

        if (!$paymentCur || !$targetCur) {
            return;
        }

        if ($paymentCur->value > $targetCur->value) {
            $set('amount', round($originalAmount / $exchangeRate, 2));
        } else {
            $set('amount', round($originalAmount * $exchangeRate, 2));
        }
    }

    protected static function getConversionLabel(Get $get): string {
        $paymentCurrencyId = $get('currency_id');
        $subscriptionId = $get('subscription_id');
        $clientId = $get('client_id');

        if (!$paymentCurrencyId) {
            return 'هذا المبلع هو ما سيتم قيده في الحساب';
        }

        $targetCurrencyId = $subscriptionId
            ? Subscription::find($subscriptionId)?->currency_id
            : ($clientId ? Client::find($clientId)?->currency_id : null);

        if (!$targetCurrencyId) {
            return 'هذا المبلع هو ما سيتم قيده في الحساب';
        }
        if ($paymentCurrencyId == $targetCurrencyId) {
            return 'نفس العملة، لا يوجد تحويل';
        }

        $paymentCur = Currency::find($paymentCurrencyId);
        $targetCur = Currency::find($targetCurrencyId);

        if (!$paymentCur || !$targetCur) {
            return '';
        }

        if ($paymentCur->value > $targetCur->value) {
            return "سيتم (تقسيم) المبلغ على سعر الصرف للتحويل من {$paymentCur->currency} إلى {$targetCur->currency}";
        }

        return "سيتم (ضرب) المبلغ في سعر الصرف للتحويل من {$paymentCur->currency} إلى {$targetCur->currency}";
    }

    public static function getRelations(): array {
        return [
            //
        ];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
