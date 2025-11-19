<?php

namespace App\Providers;

use App\Events\InvoiceGenerated;
use App\Events\LowStockDetected;
use App\Events\OrderStatusChanged;
use App\Listeners\SendOrderNotificationListener;
use App\Listeners\SendLowStockAlertListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderStatusChanged::class => [
            SendOrderNotificationListener::class,
        ],
        LowStockDetected::class => [
            SendLowStockAlertListener::class,
        ],
        InvoiceGenerated::class => [
            // SendInvoiceListener will be added here
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
