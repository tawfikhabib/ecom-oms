<?php

namespace App\Services;

use App\Events\LowStockDetected;
use App\Models\InventoryMovement;
use App\Models\Product;

class InventoryService
{
    /**
     * Deduct inventory from product
     *
     * @param Product $product
     * @param int $quantity
     * @param string $referenceType
     * @param int|null $referenceId
     * @param string|null $notes
     * @return bool
     */
    public function deductInventory(
        Product $product,
        int $quantity,
        string $referenceType = 'order',
        ?int $referenceId = null,
        ?string $notes = null
    ): bool {
        if ($product->quantity < $quantity) {
            throw new \Exception("Insufficient inventory for product {$product->sku}");
        }

        $product->decrement('quantity', $quantity);

        // Record the movement
        InventoryMovement::create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
        ]);

        // Check for low stock
        if ($product->quantity <= $product->low_stock_threshold) {
            event(new LowStockDetected($product));
        }

        return true;
    }

    /**
     * Restore inventory to product (on cancellation)
     *
     * @param Product $product
     * @param int $quantity
     * @param string $referenceType
     * @param int|null $referenceId
     * @param string|null $notes
     * @return bool
     */
    public function restoreInventory(
        Product $product,
        int $quantity,
        string $referenceType = 'order',
        ?int $referenceId = null,
        ?string $notes = null
    ): bool {
        $product->increment('quantity', $quantity);

        // Record the movement
        InventoryMovement::create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_RETURN,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
        ]);

        return true;
    }

    /**
     * Adjust inventory (manual adjustment)
     *
     * @param Product $product
     * @param int $quantity
     * @param string|null $notes
     * @return bool
     */
    public function adjustInventory(
        Product $product,
        int $quantity,
        ?string $notes = null
    ): bool {
        if ($quantity > 0) {
            $product->increment('quantity', $quantity);
            $type = InventoryMovement::TYPE_IN;
        } else {
            $product->decrement('quantity', abs($quantity));
            $type = InventoryMovement::TYPE_ADJUSTMENT;
        }

        // Record the movement
        InventoryMovement::create([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => abs($quantity),
            'notes' => $notes,
        ]);

        return true;
    }

    /**
     * Get inventory movement history
     *
     * @param Product $product
     * @return mixed
     */
    public function getMovementHistory(Product $product)
    {
        return $product->inventoryMovements()
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }
}
