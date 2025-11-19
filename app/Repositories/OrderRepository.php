<?php

namespace App\Repositories;

use App\Models\Order;

class OrderRepository
{
    /**
     * Create an order
     *
     * @param array $data
     * @return Order
     */
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    /**
     * Update an order
     *
     * @param Order $order
     * @param array $data
     * @return Order
     */
    public function update(Order $order, array $data): Order
    {
        $order->update($data);
        return $order->fresh();
    }

    /**
     * Delete an order
     *
     * @param Order $order
     * @return bool
     */
    public function delete(Order $order): bool
    {
        return $order->delete();
    }

    /**
     * Find order by ID with relations
     *
     * @param int $id
     * @return Order|null
     */
    public function findById(int $id): ?Order
    {
        return Order::with(['customer', 'items.product', 'items.variant', 'invoice'])->find($id);
    }

    /**
     * Get orders by customer with pagination
     *
     * @param int $customerId
     * @param int $perPage
     * @return mixed
     */
    public function getByCustomer(int $customerId, int $perPage = 15)
    {
        return Order::where('customer_id', $customerId)
            ->with('items.product')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get all orders with pagination
     *
     * @param int $perPage
     * @return mixed
     */
    public function getAll(int $perPage = 15)
    {
        return Order::with(['customer', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get orders by status
     *
     * @param string $status
     * @param int $perPage
     * @return mixed
     */
    public function getByStatus(string $status, int $perPage = 15)
    {
        return Order::where('status', $status)
            ->with(['customer', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
