<?php

namespace App\Policies;

use App\Models\User;

/**
 * Permission checks for orders. Which orders a user sees is decided by Order::visibleTo();
 * viewing one order, editing and deleting are added with those endpoints.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view_any') || $user->can('orders.view_assigned');
    }

    public function create(User $user): bool
    {
        return $user->can('orders.create');
    }
}
