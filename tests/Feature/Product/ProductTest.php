<?php

namespace Tests\Feature\Product;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;
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
        $this->token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($this->vendor);
    }

    /**
     * Test vendor can create product
     */
    public function test_vendor_can_create_product(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Test Product',
            'sku' => 'SKU-001',
            'description' => 'A test product',
            'price' => 99.99,
            'cost' => 50.00,
            'quantity' => 100,
            'low_stock_threshold' => 10,
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'sku', 'price', 'quantity'],
            ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-001',
        ]);
    }

    /**
     * Test vendor can list own products
     */
    public function test_vendor_can_list_own_products(): void
    {
        Product::factory(5)->create(['vendor_id' => $this->vendor->id]);

        $response = $this->getJson('/api/v1/products', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['id', 'name', 'sku'],
                ],
                'pagination',
            ])
            ->assertJsonCount(5, 'data');
    }

    /**
     * Test vendor can update own product
     */
    public function test_vendor_can_update_own_product(): void
    {
        $product = Product::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->putJson(
            "/api/v1/products/{$product->id}",
            [
                'name' => 'Updated Product Name',
                'price' => 149.99,
            ],
            [
                'Authorization' => "Bearer {$this->token}",
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product Name',
            'price' => 149.99,
        ]);
    }

    /**
     * Test vendor cannot update another vendor's product
     */
    public function test_vendor_cannot_update_another_vendor_product(): void
    {
        $other_vendor = User::factory()->create();
        $other_vendor->assignRole('vendor');
        $product = Product::factory()->create(['vendor_id' => $other_vendor->id]);

        $response = $this->putJson(
            "/api/v1/products/{$product->id}",
            ['name' => 'Updated Name'],
            [
                'Authorization' => "Bearer {$this->token}",
            ]
        );

        $response->assertStatus(403);
    }

    /**
     * Test vendor can delete own product
     */
    public function test_vendor_can_delete_own_product(): void
    {
        $product = Product::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->deleteJson(
            "/api/v1/products/{$product->id}",
            [],
            [
                'Authorization' => "Bearer {$this->token}",
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /**
     * Test can search products
     */
    public function test_can_search_products(): void
    {
        Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Searchable Product',
            'sku' => 'SEARCH-001',
        ]);

        $response = $this->getJson('/api/v1/products/search?q=Searchable', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['id', 'name'],
                ],
            ]);
    }

    /**
     * Test can get low stock products
     */
    public function test_can_get_low_stock_products(): void
    {
        Product::factory()->create([
            'vendor_id' => $this->vendor->id,
            'quantity' => 5,
            'low_stock_threshold' => 10,
        ]);

        $response = $this->getJson('/api/v1/products/low-stock', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }
}
