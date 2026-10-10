<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Session (cookie) login for the SPA via Sanctum's stateful "web" guard.
 */
class AuthService
{
    /**
     * @param  array{email: string, password: string}  $credentials
     *
     * @throws ValidationException when the credentials are wrong or the account is inactive
     */
    public function login(array $credentials, bool $remember, Session $session): User
    {
        // is_active is part of the lookup, so an inactive account fails exactly like a wrong
        // password: one generic message, nothing that reveals whether the email exists.
        if (! Auth::guard('web')->attempt([...$credentials, 'is_active' => true], $remember)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // New session id after login prevents session fixation.
        $session->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }

    public function logout(Session $session): void
    {
        Auth::guard('web')->logout();

        $session->invalidate();
        $session->regenerateToken();
    }
}
