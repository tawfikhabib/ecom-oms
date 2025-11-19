<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected OrderRepository $orderRepository;
    protected InventoryService $inventoryService;

    public function __construct(
        OrderRepository $orderRepository,
        InventoryService $inventoryService
    ) {
        $this->orderRepository = $orderRepository;
        $this->inventoryService = $inventoryService;
    }

    /**
     * Create an order
     *
     * @param int $customerId
     * @param array $items
     * @param array $data
     * @return Order
     */
    public function createOrder(int $customerId, array $items, array $data = []): Order
    {
        return DB::transaction(function () use ($customerId, $items, $data) {
            $data['customer_id'] = $customerId;
            $data['order_number'] = $this->generateOrderNumber();
            $data['status'] = Order::STATUS_PENDING;

            $order = $this->orderRepository->create($data);

            // Add items to order
            $totalAmount = 0;
            foreach ($items as $item) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['quantity'] * $item['price'],
                ]);
                $totalAmount += $orderItem->subtotal;
            }

            $order->update([
                'total_amount' => $totalAmount + ($data['tax_amount'] ?? 0) + ($data['shipping_amount'] ?? 0),
            ]);

            return $order;
        });
    }

    /**
     * Confirm order and deduct inventory
     *
     * @param Order $order
     * @return Order
     */
    public function confirmOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // Deduct inventory for all items
            foreach ($order->items as $item) {
                $this->inventoryService->deductInventory(
                    $item->product,
                    $item->quantity,
                    'order',
                    $order->id,
                    "Order {$order->order_number}"
                );
            }

            // Update order status
            $order->update(['status' => Order::STATUS_PROCESSING]);
            event(new \App\Events\OrderStatusChanged($order));

            return $order;
        });
    }

    /**
     * Ship order
     *
     * @param Order $order
     * @return Order
     */
    public function shipOrder(Order $order): Order
    {
        $order->update([
            'status' => Order::STATUS_SHIPPED,
            'shipped_at' => now(),
        ]);
        event(new \App\Events\OrderStatusChanged($order));
        return $order;
    }

    /**
     * Deliver order
     *
     * @param Order $order
     * @return Order
     */
    public function deliverOrder(Order $order): Order
    {
        $order->update([
            'status' => Order::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
        event(new \App\Events\OrderStatusChanged($order));
        return $order;
    }

    /**
     * Cancel order and restore inventory
     *
     * @param Order $order
     * @param string|null $reason
     * @return Order
     */
    public function cancelOrder(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            if ($order->status === Order::STATUS_CANCELLED) {
                throw new \Exception('Order is already cancelled');
            }

            // Restore inventory only if order was confirmed
            if (in_array($order->status, [Order::STATUS_PROCESSING, Order::STATUS_SHIPPED])) {
                foreach ($order->items as $item) {
                    $this->inventoryService->restoreInventory(
                        $item->product,
                        $item->quantity,
                        'order',
                        $order->id,
                        "Order {$order->order_number} cancelled - {$reason}"
                    );
                }
            }

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
            event(new \App\Events\OrderStatusChanged($order));

            return $order;
        });
    }

    /**
     * Get order by ID
     *
     * @param int $id
     * @return Order|null
     */
    public function getOrderById(int $id): ?Order
    {
        return $this->orderRepository->findById($id);
    }

    /**
     * Get customer orders
     *
     * @param int $customerId
     * @param int $perPage
     * @return mixed
     */
    public function getCustomerOrders(int $customerId, int $perPage = 15)
    {
        return $this->orderRepository->getByCustomer($customerId, $perPage);
    }

    /**
     * Generate unique order number
     *
     * @return string
     */
    protected function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
