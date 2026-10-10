<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Base class for business-rule failures thrown by services (e.g. insufficient stock,
 * version conflict). bootstrap/app.php renders them with their own HTTP status.
 */
abstract class BusinessException extends RuntimeException
{
    /**
     * HTTP status for this failure; 422 unless a subclass says otherwise (e.g. 409).
     */
    public function status(): int
    {
        return 422;
    }

    /**
     * Optional field-level details returned under "errors".
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return [];
    }
}
