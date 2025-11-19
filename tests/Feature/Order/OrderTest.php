<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $vendor;
    protected Product $product;
    protected string $token;

    public function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'vendor']);
        Role::firstOrCreate(['name' => 'customer']);

        $this->vendor = User::factory()->create();
        $this->vendor->assignRole('vendor');

        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');
        $this->token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($this->customer);

        $this->product = Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'quantity' => 100,
        ]);
    }

    /**
     * Test customer can create order
     */
    public function test_customer_can_create_order(): void
    {
        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'price' => $this->product->price,
                ],
            ],
            'tax_amount' => 10.00,
            'shipping_amount' => 5.00,
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'order_number', 'status', 'total_amount'],
            ]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'status' => Order::STATUS_PENDING,
        ]);
    }

    /**
     * Test customer can list own orders
     */
    public function test_customer_can_list_own_orders(): void
    {
        Order::factory(3)->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson('/api/v1/orders', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['id', 'order_number', 'status'],
                ],
                'pagination',
            ])
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test customer can view own order
     */
    public function test_customer_can_view_own_order(): void
    {
        $order = Order::factory()->create(['customer_id' => $this->customer->id]);

        $response = $this->getJson("/api/v1/orders/{$order->id}", [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'order_number', 'status'],
            ]);
    }

    /**
     * Test customer cannot view another customer's order
     */
    public function test_customer_cannot_view_another_customer_order(): void
    {
        $other_customer = User::factory()->create();
        $other_customer->assignRole('customer');
        $order = Order::factory()->create(['customer_id' => $other_customer->id]);

        $response = $this->getJson("/api/v1/orders/{$order->id}", [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test order confirmation deducts inventory
     */
    public function test_order_confirmation_deducts_inventory(): void
    {
        $order = Order::factory()->create(['customer_id' => $this->customer->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'price' => $this->product->price,
        ]);

        $initial_quantity = $this->product->quantity;

        $response = $this->postJson("/api/v1/orders/{$order->id}/confirm", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        $this->product->refresh();
        $this->assertEquals($initial_quantity - 10, $this->product->quantity);
    }

    /**
     * Test order cancellation restores inventory
     */
    public function test_order_cancellation_restores_inventory(): void
    {
        $order = Order::factory(1)->create([
            'customer_id' => $this->customer->id,
            'status' => Order::STATUS_PROCESSING,
        ])->first();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'price' => $this->product->price,
        ]);

        // Deduct inventory
        $this->product->decrement('quantity', 10);
        $initial_quantity = $this->product->quantity;

        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel", [
            'reason' => 'Changed my mind',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        $this->product->refresh();
        $this->assertEquals($initial_quantity + 10, $this->product->quantity);
    }

    /**
     * Test order status workflow
     */
    public function test_order_status_workflow(): void
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => Order::STATUS_PENDING,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        // Confirm order
        $response = $this->postJson("/api/v1/orders/{$order->id}/confirm", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);
        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals(Order::STATUS_PROCESSING, $order->status);

        // Ship order (as admin/vendor, but we're testing flow)
        // Note: In real scenario, only admin/vendor can ship
    }
}
