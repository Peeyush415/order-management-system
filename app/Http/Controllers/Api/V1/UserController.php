<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\UserFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangeUserStatusRequest;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\ManagedUserResource;
use App\Models\User;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(ListUsersRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $page = (new UserFilter($request->validated()))
            ->apply(User::query()->with('roles:id,name'))
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($page, ManagedUserResource::class);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $user = $this->users->create($request->validated());

        return ApiResponse::created(new ManagedUserResource($user->fresh('roles')), 'User created successfully');
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return ApiResponse::success(new ManagedUserResource($user->load('roles')));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $user = $this->users->update($user, $request->validated(), $request->user());

        return ApiResponse::success(new ManagedUserResource($user->fresh('roles')), 'User updated successfully');
    }

    public function updateStatus(ChangeUserStatusRequest $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $user = $this->users->changeStatus($user, $request->boolean('is_active'), $request->user());

        return ApiResponse::success(new ManagedUserResource($user->fresh('roles')), 'User status updated successfully');
    }
}
