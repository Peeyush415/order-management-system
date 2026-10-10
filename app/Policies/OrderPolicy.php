<?php

namespace App\Policies;

use App\Models\User;

/**
 * Permission checks for orders. Viewing, editing and deleting (with the staff
 * "assigned orders only" rule) are added with those endpoints.
 */
class OrderPolicy
{
    public function create(User $user): bool
    {
        return $user->can('orders.create');
    }
}
