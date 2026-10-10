<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;

/**
 * The state machine does not allow moving from the current status to the requested one.
 */
class InvalidStatusTransitionException extends BusinessException
{
    public function __construct(
        private readonly OrderStatus $from,
        private readonly OrderStatus $to,
    ) {
        parent::__construct('Invalid status transition is not allowed.');
    }

    public function errors(): array
    {
        return ['status' => ["Cannot change status from {$this->from->label()} to {$this->to->label()}."]];
    }
}
