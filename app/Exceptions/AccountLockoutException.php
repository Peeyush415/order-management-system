<?php

namespace App\Exceptions;

/**
 * A user change that would leave nobody able to manage the system
 * (deactivating yourself, removing the last active admin).
 */
class AccountLockoutException extends BusinessException {}
