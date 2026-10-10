<?php

namespace App\Exceptions;

/**
 * A customer with orders keeps its history: deactivate it instead of deleting it.
 */
class CustomerHasOrdersException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('This customer has orders and cannot be deleted. Deactivate the customer instead.');
    }
}
