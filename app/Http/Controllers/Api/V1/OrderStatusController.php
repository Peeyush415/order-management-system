<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ChangeOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatusHistoryResource;
use App\Models\Order;
use App\Services\OrderStatusService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderStatusController extends Controller
{
    public function __construct(private readonly OrderStatusService $statuses) {}

    public function store(ChangeOrderStatusRequest $request, Order $order): JsonResponse
    {
        $to = OrderStatus::from($request->validated('status'));
        Gate::authorize('changeStatus', [$order, $to]);

        $order = $this->statuses->transition($order, $to, $request->user(), $request->validated('remarks'));

        return ApiResponse::success(
            new OrderResource($order->load(OrderResource::DETAIL_RELATIONS)),
            "Order status changed to {$to->label()}",
        );
    }

    /**
     * The status buttons this user may press now. The UI shows only these;
     * the API still re-checks on every change.
     */
    public function allowedTransitions(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $transitions = collect($order->status->allowedTransitions())
            ->filter(fn (OrderStatus $to) => Gate::allows('changeStatus', [$order, $to]))
            ->map(fn (OrderStatus $to) => [
                'value' => $to->value,
                'label' => $to->label(),
                'requires_remarks' => $to === OrderStatus::Cancelled,
            ])
            ->values();

        return ApiResponse::success([
            'current' => ['value' => $order->status->value, 'label' => $order->status->label()],
            'transitions' => $transitions,
        ]);
    }

    /** Oldest first, as a timeline reads. */
    public function history(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $history = $order->statusHistories()
            ->reorder()
            ->oldest()
            ->oldest('id')
            ->with('changedBy:id,name')
            ->get();

        return ApiResponse::success(OrderStatusHistoryResource::collection($history));
    }
}
