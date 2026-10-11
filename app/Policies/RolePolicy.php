<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * The user form needs the role list, so users.manage may read roles too.
 * Changing a role's permissions needs roles.manage.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage') || $user->can('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.manage');
    }
}
