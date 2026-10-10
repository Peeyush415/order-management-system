<?php

namespace App\Exceptions;

/**
 * The requested change is not allowed in the order's current status.
 */
class OrderNotEditableException extends BusinessException {}
