<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\AuditLogFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\ListAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use App\Support\AuditValueFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only: audit rows are never edited or deleted through the API.
 */
class AuditLogController extends Controller
{
    public function index(ListAuditLogsRequest $request): JsonResponse
    {
        Gate::authorize('audit.view');

        $page = (new AuditLogFilter($request->validated()))
            ->apply(AuditLog::query()->withSubject())
            ->paginate($request->integer('per_page', 20));

        $formatter = AuditValueFormatter::for($page->getCollection());

        return ApiResponse::success(
            $page->getCollection()->map(fn (AuditLog $log) => new AuditLogResource($log, $formatter)),
            meta: ApiResponse::pageMeta($page),
        );
    }
}
