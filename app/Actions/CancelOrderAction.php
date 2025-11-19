<?php

namespace App\Actions;

use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class CancelOrderAction
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Cancel order and restore inventory
     *
     * @param Order $order
     * @param string|null $reason
     * @return Order
     * @throws \Exception
     */
    public function execute(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            if ($order->status === Order::STATUS_CANCELLED) {
                throw new \Exception("Order is already cancelled");
            }

            if ($order->status === Order::STATUS_DELIVERED) {
                throw new \Exception("Cannot cancel a delivered order");
            }

            // Restore inventory only if order was confirmed or shipped
            if (in_array($order->status, [Order::STATUS_PROCESSING, Order::STATUS_SHIPPED])) {
                foreach ($order->items as $item) {
                    $this->inventoryService->restoreInventory(
                        $item->product,
                        $item->quantity,
                        'order',
                        $order->id,
                        "Order cancelled - {$reason}"
                    );
                }
            }

            // Update order status
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            // Dispatch event for notifications
            event(new \App\Events\OrderStatusChanged($order));

            return $order->fresh();
        });
    }
}
