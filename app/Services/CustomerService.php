<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Exceptions\CustomerHasOrdersException;
use App\Models\Customer;
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
