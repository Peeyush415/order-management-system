<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function summary(DashboardRequest $request): JsonResponse
    {
        Gate::authorize('dashboard.view');

        return ApiResponse::success($this->dashboard->summary($request->from(), $request->to()));
    }

    public function revenueTrend(DashboardRequest $request): JsonResponse
    {
        Gate::authorize('dashboard.view');

        return ApiResponse::success($this->dashboard->revenueTrend($request->period(), $request->from(), $request->to()));
    }

    public function statusDistribution(DashboardRequest $request): JsonResponse
    {
        Gate::authorize('dashboard.view');

        return ApiResponse::success($this->dashboard->statusDistribution($request->from(), $request->to()));
    }

    public function myWork(Request $request): JsonResponse
    {
        Gate::authorize('orders.view_assigned');

        return ApiResponse::success($this->dashboard->myWork($request->user()));
    }
}
