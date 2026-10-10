<?php

namespace App\Exceptions;

/**
 * A product passed validation but was deactivated or removed before the order was saved.
 */
class ProductUnavailableException extends BusinessException
{
    public function __construct(private readonly int $productId)
    {
        parent::__construct("Product #{$productId} is no longer available.");
    }

    public function errors(): array
    {
        return ['items' => [$this->getMessage()]];
    }
}
