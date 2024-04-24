<?php

namespace App\Exception;

class StoreNotFoundException extends \Exception
{
    public function __construct()
    {
        return parent::__construct('Stock not found in any store');
    }
}
