<?php

namespace App\Exception;

class OutOfStockException extends \Exception
{
    public function __construct(float $allowedQuantity)
    {
        parent::__construct("Allowed $allowedQuantity pieces only");
    }
}
