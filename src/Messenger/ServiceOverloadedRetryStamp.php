<?php

namespace App\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class ServiceOverloadedRetryStamp implements StampInterface
{
    private int $retryCount;

    public function __construct(int $retryCount = 0)
    {
        $this->retryCount = $retryCount;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }
}
