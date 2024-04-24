<?php

namespace App\Logger;

class LoggerStorage
{
    private ?string $sessionId;

    public function __construct()
    {
        $this->clear();
    }

    public function init(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getContext(): string
    {
        if (!$this->sessionId) {
            return '';
        }

        return $this->sessionId;
    }

    public function clear(): void
    {
        $this->sessionId = null;
    }
}
