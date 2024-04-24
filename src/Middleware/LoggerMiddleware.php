<?php

namespace App\Middleware;

use App\Logger\LoggerStorage;
use App\Messenger\UniqueIdStamp;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class LoggerMiddleware implements MiddlewareInterface
{
    private RequestStack $requestStack;
    private LoggerStorage $loggerStorage;

    public function __construct(RequestStack $requestStack, LoggerStorage $loggerStorage)
    {
        $this->requestStack = $requestStack;
        $this->loggerStorage = $loggerStorage;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(UniqueIdStamp::class) === null) {
            try {
                $session = $this->requestStack->getSession();
            } catch (SessionNotFoundException $exception) {
                return $stack->next()->handle($envelope, $stack);
            }

            $sessionId = substr($session->getId(), 0, 8) ?: '????????';

            $uniqueIdStamp = new UniqueIdStamp($sessionId);

            $envelope = $stack->next()->handle($envelope->with($uniqueIdStamp), $stack);
        } else if ($envelope->last(ReceivedStamp::class)) {
            $uniqueIdStamp = $envelope->last(UniqueIdStamp::class);
            if ($uniqueIdStamp instanceof UniqueIdStamp && $uniqueIdStamp->getUniqueId()) {
                $this->loggerStorage->init($uniqueIdStamp->getUniqueId());

                $envelope = $stack->next()->handle($envelope, $stack);
            }
        }

        return $envelope;
    }
}
