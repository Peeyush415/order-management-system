<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Orders can be assigned only to active managers or staff (not admins, not deactivated users).
 */
class AssignableUser implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $assignable = User::query()
            ->whereKey($value)
            ->where('is_active', true)
            ->role(['manager', 'staff'])
            ->exists();

        if (! $assignable) {
            $fail('The assignee must be an active manager or staff member.');
        }
    }
}
