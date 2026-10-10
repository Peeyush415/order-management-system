<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderNoteRequest;
use App\Http\Resources\OrderNoteResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderNoteController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /** Newest first. */
    public function index(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $notes = $order->notes()
            ->with('user:id,name')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ApiResponse::paginated($notes, OrderNoteResource::class);
    }

    public function store(StoreOrderNoteRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('addNote', $order);

        $note = $this->orders->addNote($order, $request->validated('body'), $request->user());

        return ApiResponse::created(new OrderNoteResource($note->load('user:id,name')), 'Note added successfully');
    }
}
