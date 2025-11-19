<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LowStockAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Product $product;

    /**
     * Create a new job instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Send low stock alert notification to vendor
        // Get vendor
        $vendor = $this->product->vendor;

        // TODO: Implement mailable class for low stock alert
        // Mail::to($vendor->email)->send(
        //     new LowStockAlertMailable($this->product)
        // );

        // Could also log to a monitoring system or database table
    }
}
