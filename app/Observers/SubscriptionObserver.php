<?php

namespace App\Observers;

use App\Models\Subscription;

class SubscriptionObserver
{
    /**
     * Handle the Subscription "saving" event.
     */
    public function saving(Subscription $subscription): void
    {
        // Calculate and set status before saving
        // $subscription->status = $subscription->calculateStatus();
    }
}
