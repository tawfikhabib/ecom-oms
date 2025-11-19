<?php

namespace App\Listeners;

use App\Events\LowStockDetected;
use App\Jobs\LowStockAlertJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLowStockAlertListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LowStockDetected $event): void
    {
        // Dispatch job to send low stock alert asynchronously
        LowStockAlertJob::dispatch($event->product);
    }
}
