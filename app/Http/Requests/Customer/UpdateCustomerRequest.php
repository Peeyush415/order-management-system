<?php

namespace App\Http\Requests\Customer;

/**
 * Same rules as create; the email uniqueness check ignores the customer being edited.
 */
class UpdateCustomerRequest extends StoreCustomerRequest {}
