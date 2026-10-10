<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /** Eager loads for the full order view, with only the columns the resource uses. */
    private const DETAIL_RELATIONS = ['customer:id,name,email', 'assignee:id,name', 'creator:id,name', 'items'];

    public function __construct(private readonly OrderService $orders) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $order = $this->orders->create($request->validated(), $request->user());

        return ApiResponse::created(new OrderResource($order->load(self::DETAIL_RELATIONS)), 'Order created successfully');
    }
}
