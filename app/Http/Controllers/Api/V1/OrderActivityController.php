<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderActivityResource;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderActivityController extends Controller
{
    /** The order's activity feed, newest first. */
    public function index(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $activities = $order->activities()
            ->with('user:id,name')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ApiResponse::paginated($activities, OrderActivityResource::class);
    }
}
