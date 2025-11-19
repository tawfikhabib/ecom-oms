<?php

namespace App\Actions;

use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class ConfirmOrderAction
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Confirm order and deduct inventory
     *
     * @param Order $order
     * @return Order
     * @throws \Exception
     */
    public function execute(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            if ($order->status !== Order::STATUS_PENDING) {
                throw new \Exception("Order must be in pending status to confirm");
            }

            // Validate and deduct inventory for all items
            foreach ($order->items as $item) {
                $product = $item->product;

                if ($product->quantity < $item->quantity) {
                    throw new \Exception("Insufficient inventory for product: {$product->sku}");
                }

                $this->inventoryService->deductInventory(
                    $product,
                    $item->quantity,
                    'order',
                    $order->id,
                    "Order confirmed: {$order->order_number}"
                );
            }

            // Update order status
            $order->update(['status' => Order::STATUS_PROCESSING]);

            // Dispatch event for notifications, invoice generation, etc.
            event(new \App\Events\OrderStatusChanged($order));

            return $order->fresh();
        });
    }
}
