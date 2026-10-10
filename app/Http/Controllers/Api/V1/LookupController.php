<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Small, rarely changing option lists the SPA needs for filters and forms,
 * in one request instead of one per dropdown.
 */
class LookupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $options = fn (array $cases) => array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases);

        return ApiResponse::success([
            'order_statuses' => $options(OrderStatus::cases()),
            'order_priorities' => $options(OrderPriority::cases()),
            // Who an order can be assigned to: active managers and staff, name only.
            'assignable_users' => User::query()
                ->select(['id', 'name'])
                ->where('is_active', true)
                ->role(['manager', 'staff'])
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->values(),
        ]);
    }
}
