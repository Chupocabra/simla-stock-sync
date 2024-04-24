<?php

namespace App\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class UniqueIdStamp implements StampInterface
{
    private string $uniqueId;

    public function __construct(string $uniqueId)
    {
        $this->uniqueId = $uniqueId;
    }
    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }
}
