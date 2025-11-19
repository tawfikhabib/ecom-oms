<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Order $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Send email notification based on order status
        $statusMessages = [
            Order::STATUS_PENDING => 'Your order has been received and is pending confirmation.',
            Order::STATUS_PROCESSING => 'Your order is being processed.',
            Order::STATUS_SHIPPED => 'Your order has been shipped!',
            Order::STATUS_DELIVERED => 'Your order has been delivered!',
            Order::STATUS_CANCELLED => 'Your order has been cancelled.',
        ];

        $message = $statusMessages[$this->order->status] ?? 'Your order status has changed.';

        // TODO: Implement mailable class for sending notification
        // Mail::to($this->order->customer->email)->send(
        //     new OrderNotificationMailable($this->order, $message)
        // );
    }
}
