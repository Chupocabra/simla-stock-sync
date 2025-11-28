<?php

namespace App\Exception;

class ServiceOverloadedException extends \Exception
{
    public function __construct(\Exception $e)
    {
        parent::__construct($e->getMessage(), $e->getCode(), $e->getPrevious());
    }
}
