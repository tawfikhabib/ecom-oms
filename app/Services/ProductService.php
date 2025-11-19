<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Models\ProductVariant;
use Illuminate\Pagination\Paginator;

class ProductService
{
    protected ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * Create a new product
     *
     * @param array $data
     * @return Product
     */
    public function createProduct(array $data): Product
    {
        // Extract variants payload if present
        $variants = $data['variants'] ?? null;
        unset($data['variants']);

        // Create product
        $product = $this->productRepository->create($data);

        // Create variants if provided
        if (is_array($variants)) {
            foreach ($variants as $v) {
                $variantData = [
                    'product_id' => $product->id,
                    'sku' => $v['sku'] ?? ($product->sku . '-' . uniqid()),
                    'name' => $v['name'] ?? null,
                    'attributes' => $v['attributes'] ?? null,
                    'price' => isset($v['price']) ? $v['price'] : $product->price,
                    'quantity' => isset($v['quantity']) ? $v['quantity'] : ($product->quantity ?? 0),
                ];
                ProductVariant::create($variantData);
            }
        }

        return $product->fresh();
    }

    /**
     * Update a product
     *
     * @param Product $product
     * @param array $data
     * @return Product
     */
    public function updateProduct(Product $product, array $data): Product
    {
        // Handle nested variants if present
        $variants = $data['variants'] ?? null;
        unset($data['variants']);

        $updated = $this->productRepository->update($product, $data);

        if (is_array($variants)) {
            foreach ($variants as $v) {
                // Update existing variant by id if provided
                if (!empty($v['id'])) {
                    $variant = ProductVariant::find($v['id']);
                    if ($variant && $variant->product_id === $product->id) {
                        $variant->update(array_filter([
                            'sku' => $v['sku'] ?? $variant->sku,
                            'name' => $v['name'] ?? $variant->name,
                            'attributes' => $v['attributes'] ?? $variant->attributes,
                            'price' => isset($v['price']) ? $v['price'] : $variant->price,
                            'quantity' => isset($v['quantity']) ? $v['quantity'] : $variant->quantity,
                        ], fn($val) => $val !== null));
                    }
                    continue;
                }

                // Match by sku if provided, otherwise create new
                if (!empty($v['sku'])) {
                    $variant = ProductVariant::firstOrNew(['sku' => $v['sku']]);
                    $variant->product_id = $product->id;
                    $variant->name = $v['name'] ?? $variant->name;
                    $variant->attributes = $v['attributes'] ?? $variant->attributes;
                    $variant->price = isset($v['price']) ? $v['price'] : ($variant->price ?? $product->price);
                    $variant->quantity = isset($v['quantity']) ? $v['quantity'] : ($variant->quantity ?? ($product->quantity ?? 0));
                    $variant->save();
                } else {
                    // create new variant without sku
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $product->sku . '-' . uniqid(),
                        'name' => $v['name'] ?? null,
                        'attributes' => $v['attributes'] ?? null,
                        'price' => isset($v['price']) ? $v['price'] : $product->price,
                        'quantity' => isset($v['quantity']) ? $v['quantity'] : ($product->quantity ?? 0),
                    ]);
                }
            }
        }

        return $this->productRepository->findById($product->id) ?? $updated;
    }

    /**
     * Delete a product
     *
     * @param Product $product
     * @return bool
     */
    public function deleteProduct(Product $product): bool
    {
        return $this->productRepository->delete($product);
    }

    /**
     * Get product by ID
     *
     * @param int $id
     * @return Product|null
     */
    public function getProductById(int $id): ?Product
    {
        return $this->productRepository->findById($id);
    }

    /**
     * Get all products for a vendor
     *
     * @param int $vendorId
     * @param int $perPage
     * @return mixed
     */
    public function getVendorProducts(int $vendorId, int $perPage = 15)
    {
        return $this->productRepository->getByVendor($vendorId, $perPage);
    }

    /**
     * Search products
     *
     * @param string $query
     * @param int $perPage
     * @return mixed
     */
    public function searchProducts(string $query, int $perPage = 15)
    {
        return $this->productRepository->search($query, $perPage);
    }

    /**
     * Get low stock products
     *
     * @return mixed
     */
    public function getLowStockProducts()
    {
        return $this->productRepository->getLowStock();
    }
}
