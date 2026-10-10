<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Exceptions\CustomerHasOrdersException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;

class CustomerService
{
    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function create(array $data, User $by): Customer
    {
        $customer = new Customer($data);
        $customer->created_by = $by->id;
        $customer->save();

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer;
    }

    public function changeStatus(Customer $customer, CustomerStatus $status): Customer
    {
        $customer->update(['status' => $status]);

        return $customer;
    }

    /**
     * Order summary for the customer detail page, in one aggregate query on the
     * (customer_id, order_date) index. Order value counts booked orders only
     * (confirmed through completed), the same definition the dashboard uses.
     *
     * @return array{total_orders: int, pending_orders: int, completed_orders: int, cancelled_orders: int, total_order_value: int}
     */
    public function stats(Customer $customer): array
    {
        $booked = [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Ready, OrderStatus::Completed];

        $row = Order::query()
            ->where('customer_id', $customer->id)
            ->toBase()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(status = ?), 0) as pending_orders', [OrderStatus::Pending->value])
            ->selectRaw('COALESCE(SUM(status = ?), 0) as completed_orders', [OrderStatus::Completed->value])
            ->selectRaw('COALESCE(SUM(status = ?), 0) as cancelled_orders', [OrderStatus::Cancelled->value])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status IN (?, ?, ?, ?) THEN grand_total ELSE 0 END), 0) as total_order_value',
                array_map(fn (OrderStatus $s) => $s->value, $booked),
            )
            ->first();

        return array_map('intval', (array) $row);
    }

    /**
     * Soft delete, only for customers without orders (including soft-deleted ones).
     *
     * @throws CustomerHasOrdersException
     */
    public function delete(Customer $customer): void
    {
        if ($customer->orders()->withTrashed()->exists()) {
            throw new CustomerHasOrdersException;
        }

        $customer->delete();
    }
}
