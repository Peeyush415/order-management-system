<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StockAdjustmentRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\InventoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProductStockController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function store(StockAdjustmentRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $product = $this->inventory->adjust(
            $product,
            $request->integer('quantity'),
            $request->validated('reason'),
            $request->user(),
        );

        return ApiResponse::success(new ProductResource($product), 'Stock adjusted successfully');
    }
}
