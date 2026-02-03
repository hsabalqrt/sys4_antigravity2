<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\AccountingStats;

class AccountingDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string $view = 'filament.pages.accounting-dashboard';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?string $navigationLabel = 'لوحة التحكم المالية';

    protected static ?string $title = 'لوحة التحكم المالية';

    protected function getHeaderWidgets(): array
    {
        return [
            AccountingStats::class,
        ];
    }
}
