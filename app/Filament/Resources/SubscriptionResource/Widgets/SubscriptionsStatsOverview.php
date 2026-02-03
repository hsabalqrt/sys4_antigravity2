<?php

namespace App\Filament\Resources\SubscriptionResource\Widgets;

use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionsStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Now that we have a 'status' column in the database, we can query it directly!
        $active = Subscription::where('status', 'active')->count();
        $expiringSoon = Subscription::where('status', 'expiring_soon')->count();
        $expired = Subscription::where('status', 'expired')->count();

        return [
            Stat::make('الاشتراكات النشطة', $active)
                ->description('حالياً')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            
            Stat::make('تنتهي قريباً', $expiringSoon)
                ->description('خلال 2 أيام')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            
            Stat::make('اشتراكات منتهية', $expired)
                ->description('بحاجة لتجديد')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
