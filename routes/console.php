<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// جدولة: فحص انتهاء الاشتراكات كل يوم في الساعة 23:47 مساءً
Schedule::command('subscriptions:check-expiry')->dailyAt('23:47');
