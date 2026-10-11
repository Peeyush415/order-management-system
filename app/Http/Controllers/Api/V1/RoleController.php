<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\UpdateRolePermissionsRequest;
use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        return ApiResponse::success(RoleResource::collection($this->roles->all()));
    }

    /**
     * Every permission, grouped by the part before the dot ("orders.cancel" → Orders),
     * so the matrix can show one block per module.
     */
    public function permissions(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $groups = Permission::query()->orderBy('id')->pluck('name')
            ->groupBy(fn (string $name) => Str::before($name, '.'))
            ->map(fn ($names, string $group) => [
                'group' => $group,
                'label' => ucfirst($group),
                'permissions' => $names->map(fn (string $name) => [
                    'name' => $name,
                    'label' => Str::of($name)->after('.')->replace('_', ' ')->ucfirst()->toString(),
                ])->values(),
            ])
            ->values();

        return ApiResponse::success($groups);
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): JsonResponse
    {
        Gate::authorize('update', $role);

        $this->roles->syncPermissions($role, $request->validated('permissions'));

        return ApiResponse::success(
            new RoleResource($this->roles->all()->firstWhere('id', $role->id)),
            'Role permissions updated successfully',
        );
    }
}
