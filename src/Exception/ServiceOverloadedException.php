<?php

namespace App\Exception;

use RetailCrm\Api\Interfaces\ApiExceptionInterface;

class ServiceOverloadedException extends \Exception
{
    public function __construct(ApiExceptionInterface $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e->getPrevious());
    }
}
