<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Filters\ProductFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ChangeProductStatusRequest;
use App\Http\Requests\Product\ListProductsRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(ListProductsRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $page = (new ProductFilter($request->validated()))
            ->apply(Product::query())
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page, ProductResource::class);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $product = $this->products->create(
            $request->safe()->except('initial_stock'),
            $request->integer('initial_stock'),
            $request->user(),
        );

        return ApiResponse::created(new ProductResource($product->fresh()), 'Product created successfully');
    }

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return ApiResponse::success(new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $product = $this->products->update($product, $request->validated());

        return ApiResponse::success(new ProductResource($product), 'Product updated successfully');
    }

    public function updateStatus(ChangeProductStatusRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $product = $this->products->changeStatus($product, ProductStatus::from($request->validated('status')));

        return ApiResponse::success(new ProductResource($product), 'Product status updated successfully');
    }
}
