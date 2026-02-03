<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Models\Client;

class DailySubscriptionSuspension extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:daily-subscription-suspension';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suspend subscriptions or clients based on debt rules';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Partial Suspension: Check each Subscription
        $subscriptions = Subscription::where('status', 'active')->get();

        foreach ($subscriptions as $subscription) {
            $client = $subscription->client;

            // حساب تاريخ انتهاء فترة السماح (تاريخ الانتهاء + عدد أيام السماح)
            $graceDate = $subscription->end_date ? $subscription->end_date->copy()->addDays($client->suspension_days ?? 0) : null;

            // --- سيناريو الاشتراك المؤخر (Deferred) ---
            if ($subscription->payment_type === 'deferred') {
                // إذا انتهى التاريخ وكان الرصيد سالباً
                if ($subscription->end_date && now()->greaterThan($subscription->end_date) && $subscription->balance < 0) {
                    // إذا لم يكن هناك سقف ائتماني ومرت فترة السماح
                    if (!$client->is_credit_allowed && ($graceDate && now()->greaterThan($graceDate))) {
                        $subscription->update(['status' => 'expired']);
                        $this->info("Subscription #{$subscription->id} (Postpaid) is expired due to debt and grace period.");
                    } else if ($client->is_credit_allowed) {
                        $this->warn("Subscription #{$subscription->id} (Postpaid) is in debt but protected by credit limit.");
                    }
                }
            }
            // --- سيناريو الاشتراك المقدم (Advance) ---
            else {
                // في المقدم، الإيقاف يعتمد فقط على انتهاء التاريخ
                if ($subscription->end_date && now()->greaterThan($subscription->end_date)) {
                    // إذا لم يكن هناك سقف ائتماني ومرت فترة السماح
                    if (!$client->is_credit_allowed && ($graceDate && now()->greaterThan($graceDate))) {
                        $subscription->update(['status' => 'expired']);
                        $this->info("Subscription #{$subscription->id} (Prepaid) is expired (end_date reached).");
                    }
                }
            }
        }

        $this->info('Suspension checks completed.');
    }
}
