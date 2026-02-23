<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

class LockAcquisitionException extends \RuntimeException
{
    public function __construct(array $productIds)
    {
        $ids = implode(', ', $productIds);
        parent::__construct(
            "Could not acquire lock for products: [{$ids}]. Please retry."
        );
    }
}
