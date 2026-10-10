<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Permission checks for orders. Lists are narrowed separately by Order::visibleTo();
 * view() applies the same rule to a single order.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view_any') || $user->can('orders.view_assigned');
    }

    /** Staff may open only the orders assigned to them. */
    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.view_any')
            || ($user->can('orders.view_assigned') && $order->assigned_to === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('orders.create');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.update');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can('orders.delete');
    }
}
