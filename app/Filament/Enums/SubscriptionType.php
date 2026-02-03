<?php

namespace App\Filament\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;

enum SubscriptionType: string implements HasLabel, HasColor, HasIcon
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function getLabel(): ?string
    {
        // return $this->name;
        return match ($this) {
            self::Weekly => 'أسبوعي',
            self::Monthly => 'شهري',
            self::Yearly => 'سنوي',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Weekly => 'warning',
            self::Monthly => 'info',
            self::Yearly => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Weekly => 'heroicon-o-clock',
            self::Monthly => 'heroicon-o-calendar',
            self::Yearly => 'heroicon-o-calendar',
        };
    }
}
