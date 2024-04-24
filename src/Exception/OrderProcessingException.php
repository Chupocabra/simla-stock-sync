<?php

namespace App\Exception;

class OrderProcessingException extends \Exception
{
    public function __construct(string $article, int $orderId, int $itemId)
    {
        parent::__construct("Article `$article` of order `$orderId` item `$itemId` already processing");
    }
}
