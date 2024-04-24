<?php

namespace App\Logger;

use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

class SessionRequestProcessor implements ProcessorInterface
{
    private $requestStack;
    private $loggerStorage;

    public function __construct(RequestStack $requestStack, LoggerStorage $loggerStorage)
    {
        $this->requestStack = $requestStack;
        $this->loggerStorage = $loggerStorage;
    }

    // this method is called for each log record; optimize it to not hurt performance
    public function __invoke(array $record): array
    {
        if (empty($this->loggerStorage->getContext())) {
            try {
                $session = $this->requestStack->getSession();
            } catch (SessionNotFoundException $e) {
                return $record;
            }
            $session->start();
            $sessionId = substr($session->getId(), 0, 8) ?: '????????';
            $record['extra']['token'] = $sessionId;
        } else {
            $record['extra']['token'] = $this->loggerStorage->getContext();
        }

        return $record;
    }
}
