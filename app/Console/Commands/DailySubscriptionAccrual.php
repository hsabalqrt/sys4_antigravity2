<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Models\Transaction;

class DailySubscriptionAccrual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:daily-subscription-accrual';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily debit transactions for postpaid subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // يتم تشغيل هذا القيد للاشتراكات "المؤخرة" (Postpaid) فقط أثناء سريان مفعولها.
        $postpaidSubscriptions = Subscription::where('payment_type', 'deferred')
            ->where('status', 'active')
            ->whereDate('end_date', '>=', now())
            ->get();

        foreach ($postpaidSubscriptions as $subscription) {
            // حساب المعدل اليومي (المبلغ / 30 يوم للشهر، أو حسب النوع)
            $days = match ($subscription->subscription_type) {
                'weekly' => 7,
                'yearly' => 365,
                default => 30,
            };

            $dailyRate = $subscription->payment_amount / $days;

            Transaction::create([
                'client_id' => $subscription->client_id,
                'subscription_id' => $subscription->id,
                'amount' => $dailyRate,
                'type' => 'debit',
                'description' => 'استحقاق يومي (دفع مؤخر) - اشتراك #' . $subscription->id,
                'transaction_date' => now(),
            ]);
        }

        $this->info('Daily accruals generated successfully.');
    }
}
