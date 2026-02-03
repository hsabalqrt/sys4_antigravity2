<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingStats extends BaseWidget
{
    protected function getStats(): array
    {
        $totalDebits = Transaction::where('type', 'debit')->sum('amount');
        $totalCredits = Transaction::where('type', 'credit')->sum('amount');
        $totalUnpaid = $totalDebits - $totalCredits;

        return [
            Stat::make('إجمالي المفوتر (الرسوم)', number_format($totalDebits) . ' YER')
                ->description('إجمالي قيمة جميع رسوم الاشتراكات')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
            Stat::make('إجمالي المسدد (المحصل)', number_format($totalCredits) . ' YER')
                ->description('إجمالي المبالغ المدفوعة فعلياً')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('إجمالي المتبقي', number_format($totalUnpaid) . ' YER')
                ->description('إجمالي الديون المتبقية على العملاء')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger'),
        ];
    }
}
