<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(LoginRequest $request): JsonResponse
    {
        // Sanctum starts a session only for requests from a stateful domain (Referer/Origin).
        abort_unless($request->hasSession(), 400, 'Login needs a session: call it from the web app, or send a Referer header listed in SANCTUM_STATEFUL_DOMAINS.');

        $user = $this->auth->login($request->credentials(), $request->boolean('remember'), $request->session());

        return ApiResponse::success(new UserResource($user), 'Logged in successfully');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->session());

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }
}
