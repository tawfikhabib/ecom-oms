<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
        $this->middleware('auth:api');
        $this->middleware('permission:view-products', ['only' => ['index', 'show']]);
        $this->middleware('permission:create-products', ['only' => 'store']);
        $this->middleware('permission:edit-products', ['only' => 'update']);
        $this->middleware('permission:delete-products', ['only' => 'destroy']);
    }

    /**
     * Get all products
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $products = $this->productService->getVendorProducts(auth()->id(), $perPage);

        return response()->json([
            'message' => 'Products retrieved successfully',
            'data' => ProductResource::collection($products),
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ], 200);
    }

    /**
     * Store a new product
     *
     * @param StoreProductRequest $request
     * @return JsonResponse
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['vendor_id'] = auth()->id();

        $product = $this->productService->createProduct($data);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => new ProductResource($product),
        ], 201);
    }

    /**
     * Get a single product
     *
     * @param Product $product
     * @return JsonResponse
     */
    public function show(Product $product): JsonResponse
    {
        // Check ownership for vendors
        if (auth()->user()->hasRole('vendor') && $product->vendor_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => new ProductResource($product),
        ], 200);
    }

    /**
     * Update a product
     *
     * @param UpdateProductRequest $request
     * @param Product $product
     * @return JsonResponse
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        // Check ownership for vendors
        if (auth()->user()->hasRole('vendor') && $product->vendor_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updated = $this->productService->updateProduct($product, $request->validated());

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => new ProductResource($updated),
        ], 200);
    }

    /**
     * Delete a product
     *
     * @param Product $product
     * @return JsonResponse
     */
    public function destroy(Product $product): JsonResponse
    {
        // Check ownership for vendors
        if (auth()->user()->hasRole('vendor') && $product->vendor_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->productService->deleteProduct($product);

        return response()->json([
            'message' => 'Product deleted successfully',
        ], 200);
    }

    /**
     * Search products
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $perPage = $request->query('per_page', 15);

        if (empty($query)) {
            return response()->json([
                'message' => 'Search query is required',
            ], 400);
        }

        $products = $this->productService->searchProducts($query, $perPage);

        return response()->json([
            'message' => 'Products search results',
            'data' => ProductResource::collection($products),
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ], 200);
    }

    /**
     * Get low stock products
     *
     * @return JsonResponse
     */
    public function lowStock(): JsonResponse
    {
        $products = $this->productService->getLowStockProducts();

        return response()->json([
            'message' => 'Low stock products retrieved',
            'data' => ProductResource::collection($products),
        ], 200);
    }
}
