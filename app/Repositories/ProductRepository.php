<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Pagination\Paginator;

class ProductRepository
{
    /**
     * Create a product
     *
     * @param array $data
     * @return Product
     */
    public function create(array $data): Product
    {
        return Product::create($data);
    }

    /**
     * Update a product
     *
     * @param Product $product
     * @param array $data
     * @return Product
     */
    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh();
    }

    /**
     * Delete a product
     *
     * @param Product $product
     * @return bool
     */
    public function delete(Product $product): bool
    {
        return $product->delete();
    }

    /**
     * Find product by ID with relations
     *
     * @param int $id
     * @return Product|null
     */
    public function findById(int $id): ?Product
    {
        return Product::with(['vendor', 'variants'])->find($id);
    }

    /**
     * Get all products for a vendor with pagination
     *
     * @param int $vendorId
     * @param int $perPage
     * @return mixed
     */
    public function getByVendor(int $vendorId, int $perPage = 15)
    {
        return Product::where('vendor_id', $vendorId)
            ->with('variants')
            ->paginate($perPage);
    }

    /**
     * Search products by name or SKU
     *
     * @param string $query
     * @param int $perPage
     * @return mixed
     */
    public function search(string $query, int $perPage = 15)
    {
        return Product::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhereRaw("MATCH(name, description) AGAINST(? IN BOOLEAN MODE)", [$query]);
            })
            ->with(['vendor', 'variants'])
            ->paginate($perPage);
    }

    /**
     * Get products with low stock
     *
     * @return mixed
     */
    public function getLowStock()
    {
        return Product::whereRaw('quantity <= low_stock_threshold')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get all active products
     *
     * @param int $perPage
     * @return mixed
     */
    public function getAllActive(int $perPage = 15)
    {
        return Product::where('is_active', true)
            ->with(['vendor', 'variants'])
            ->paginate($perPage);
    }
}
