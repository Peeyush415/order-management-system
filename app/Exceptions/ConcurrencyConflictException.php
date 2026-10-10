<?php

namespace App\Exceptions;

/**
 * Optimistic locking: the client edited an older version of the order than the one saved now.
 */
class ConcurrencyConflictException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('This order was changed by someone else. Reload and try again.');
    }

    public function status(): int
    {
        return 409;
    }
}
