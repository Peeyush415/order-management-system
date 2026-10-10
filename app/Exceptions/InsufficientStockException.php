<?php

namespace App\Exceptions;

/**
 * Not enough stock to confirm an order or apply a negative adjustment. Thrown inside
 * the transaction, so every stock change made before it is rolled back.
 */
class InsufficientStockException extends BusinessException
{
    public function __construct(string $productName, int $available, int $requested)
    {
        parent::__construct("Insufficient stock for {$productName}: {$available} available, {$requested} requested.");
    }

    public function errors(): array
    {
        return ['items' => [$this->getMessage()]];
    }
}
