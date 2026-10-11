<?php

namespace App\Exceptions;

/**
 * The admin role always keeps every permission, so an admin can never lock everyone out.
 */
class RoleLockedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct('The admin role always has every permission and cannot be changed.');
    }
}
