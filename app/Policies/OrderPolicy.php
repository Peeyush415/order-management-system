<?php

namespace App\Policies;

use App\Enums\OrderStatus;
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

    public function assign(User $user, Order $order): bool
    {
        return $user->can('orders.assign') && $this->view($user, $order);
    }

    /** Staff may add notes only to orders assigned to them. */
    public function addNote(User $user, Order $order): bool
    {
        return $user->can('notes.create') && $this->view($user, $order);
    }

    /** Route-level check: may this user change this order's status at all? */
    public function changeStatusOf(User $user, Order $order): bool
    {
        return $user->can('orders.change_status') && $this->view($user, $order);
    }

    /**
     * May this user move this order to $to? Whether the move itself is valid is the
     * state machine's job (OrderStatus::canTransitionTo); this only decides who.
     *
     *   processing / ready / completed → orders.change_status (staff on their own orders)
     *   draft / pending / confirmed    → orders.update (commercial decisions: admin, manager)
     *   cancelled                      → orders.cancel; from ready also orders.delete (admin only)
     */
    public function changeStatus(User $user, Order $order, OrderStatus $to): bool
    {
        if (! $this->changeStatusOf($user, $order)) {
            return false;
        }

        return match ($to) {
            OrderStatus::Processing, OrderStatus::Ready, OrderStatus::Completed => true,
            OrderStatus::Draft, OrderStatus::Pending, OrderStatus::Confirmed => $user->can('orders.update'),
            OrderStatus::Cancelled => $user->can('orders.cancel')
                && ($order->status !== OrderStatus::Ready || $user->can('orders.delete')),
        };
    }
}
