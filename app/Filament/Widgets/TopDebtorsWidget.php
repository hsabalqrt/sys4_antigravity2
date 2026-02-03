<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopDebtorsWidget extends BaseWidget
{
    protected static ?string $heading = 'أعلى العملاء مديونية';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Client::query()
                    ->withSum(['transactions as total_credits' => function ($query) {
                        $query->where('type', 'credit');
                    }], 'amount')
                    ->withSum(['transactions as total_debits' => function ($query) {
                        $query->where('type', 'debit');
                    }], 'amount')
                    ->groupBy('id')
                    ->havingRaw('(COALESCE(total_credits, 0) - COALESCE(total_debits, 0)) < 0')
                    ->orderByRaw('(COALESCE(total_credits, 0) - COALESCE(total_debits, 0)) ASC')
                    ->take(10),
            )
            ->columns([
                Tables\Columns\TextColumn::make('company')
                    ->label('الشركة')
                    ->searchable(),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('المسؤول'),
                Tables\Columns\TextColumn::make('contact_number')
                    ->label('الهاتف'),
                Tables\Columns\TextColumn::make('balance_amount')
                    ->label('المديونية')
                    ->state(fn(Client $record) => $record->total_credits - $record->total_debits)
                    ->money('YER')
                    ->color('danger')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('(COALESCE(total_credits, 0) - COALESCE(total_debits, 0)) ' . $direction);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('عرض')
                    ->url(fn(Client $record): string => \App\Filament\Resources\ClientResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
