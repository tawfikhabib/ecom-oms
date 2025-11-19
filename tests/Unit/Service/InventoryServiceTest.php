<?php

namespace Tests\Unit\Service;

use App\Events\LowStockDetected;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected Product $product;
    protected User $vendor;

    public function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = new InventoryService();
        $this->vendor = User::factory()->create();
        $this->product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'quantity' => 100,
            'low_stock_threshold' => 10,
        ]);
    }

    /**
     * Test deduct inventory
     */
    public function test_deduct_inventory(): void
    {
        $initialQuantity = $this->product->quantity;

        $this->inventoryService->deductInventory($this->product, 20);

        $this->product->refresh();
        $this->assertEquals($initialQuantity - 20, $this->product->quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => 20,
        ]);
    }

    /**
     * Test deduct inventory throws exception when insufficient stock
     */
    public function test_deduct_inventory_throws_exception_when_insufficient(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Insufficient inventory");

        $this->inventoryService->deductInventory($this->product, 150);
    }

    /**
     * Test deduct inventory triggers low stock alert
     */
    public function test_deduct_inventory_triggers_low_stock_alert(): void
    {
        Event::fake();

        $this->product->update(['quantity' => 15]);
        $this->inventoryService->deductInventory($this->product, 10);

        Event::assertDispatched(LowStockDetected::class);
    }

    /**
     * Test restore inventory
     */
    public function test_restore_inventory(): void
    {
        $this->product->update(['quantity' => 50]);
        $initialQuantity = $this->product->quantity;

        $this->inventoryService->restoreInventory($this->product, 25);

        $this->product->refresh();
        $this->assertEquals($initialQuantity + 25, $this->product->quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => InventoryMovement::TYPE_RETURN,
            'quantity' => 25,
        ]);
    }

    /**
     * Test adjust inventory
     */
    public function test_adjust_inventory(): void
    {
        $initialQuantity = $this->product->quantity;

        $this->inventoryService->adjustInventory($this->product, 50);

        $this->product->refresh();
        $this->assertEquals($initialQuantity + 50, $this->product->quantity);
    }

    /**
     * Test get movement history
     */
    public function test_get_movement_history(): void
    {
        $this->inventoryService->deductInventory($this->product, 5);
        $this->inventoryService->deductInventory($this->product, 10);
        $this->inventoryService->restoreInventory($this->product, 5);

        $history = $this->inventoryService->getMovementHistory($this->product);

        $this->assertCount(3, $history->items());
    }
}
