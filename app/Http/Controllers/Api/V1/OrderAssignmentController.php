<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AssignOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderAssignmentController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function store(AssignOrderRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('assign', $order);

        $assigneeId = $request->validated('assigned_to');

        $order = $this->orders->assign($order, $assigneeId === null ? null : (int) $assigneeId, $request->user())
            ->load(OrderResource::DETAIL_RELATIONS);

        $message = $order->assignee ? "Order assigned to {$order->assignee->name}" : 'Order unassigned';

        return ApiResponse::success(new OrderResource($order), $message);
    }
}
