<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\OrderFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ListOrdersRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderListResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /** Columns the list needs; nothing else is read from a 1M-row table. */
    private const LIST_COLUMNS = ['id', 'order_number', 'customer_id', 'assigned_to', 'status', 'priority', 'order_date', 'grand_total', 'created_at'];

    private const LIST_RELATIONS = ['customer:id,name,email', 'assignee:id,name'];

    /** Eager loads for the full order view, with only the columns the resource uses. */
    private const DETAIL_RELATIONS = ['customer:id,name,email', 'assignee:id,name', 'creator:id,name', 'items'];

    public function __construct(private readonly OrderService $orders) {}

    public function index(ListOrdersRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $query = Order::query()
            ->visibleTo($request->user())
            ->select(array_map(fn (string $column) => "orders.{$column}", self::LIST_COLUMNS))
            ->with(self::LIST_RELATIONS);

        $page = (new OrderFilter($request->validated()))
            ->apply($query)
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page, OrderListResource::class);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $order = $this->orders->create($request->validated(), $request->user());

        return ApiResponse::created(new OrderResource($order->load(self::DETAIL_RELATIONS)), 'Order created successfully');
    }
}
