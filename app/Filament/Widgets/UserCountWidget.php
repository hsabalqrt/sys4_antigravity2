<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use App\Models\Category;

/**
 * ودجة Filament لعرض إحصائيات سريعة.
 *
 * تعرض هذه الودجة إجمالي عدد المستخدمين في النظام.
 */
class UserCountWidget extends BaseWidget
{
    /**
     * يقوم بإرجاع مصفوفة من كائنات `Stat` لعرضها في الودجة.
     *
     * @return array
     */
    protected function getStats(): array
    {
        return [
            Stat::make('عدد المستخدمين', User::count())
                ->description('إجمالي عدد المستخدمين')
                ->color('success'),
        ];
    }
}
