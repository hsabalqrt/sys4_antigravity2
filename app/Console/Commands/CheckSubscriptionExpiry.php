<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckSubscriptionExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all subscriptions and update their status based on expiry date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking subscription expirations...');

        // Verify all subscriptions that are not already expired
        // We can also check ALL in case an expired one was extended but status not updated (though observer handles saves)
        // To be safe and ensure daily consistency, let's check all 'active' and 'expiring_soon'.
        $subscriptions = \App\Models\Subscription::whereIn('status', ['active', 'expiring_soon'])->get();

        $count = 0;
        foreach ($subscriptions as $subscription) {
            // calculatedStatus() uses the current date (now()) vs end_date
            $newStatus = $subscription->calculateStatus();

            if ($subscription->status !== $newStatus) {
                // Updating will trigger the Observer 'saving' event which sets the status, 
                // but since we are manually setting it/checking it, we can just save.
                // Actually, just calling save() is enough because our Observer calculates status on save.
                // However, to force the update if no other field changed, we might need to be careful.
                // But Eloquent dirty check might prevent save if nothing changed.
                // So we explicitly set it.
                
                $subscription->status = $newStatus;
                $subscription->save();
                $count++;
                
                $this->info("Subscription #{$subscription->id} updated to {$newStatus}");
            }
        }

        $this->info("Checked {$subscriptions->count()} subscriptions. Updated {$count}.");
    }
}
