<?php

namespace App\Middleware;

use App\Exception\ServiceOverloadedException;
use App\Messenger\ServiceOverloadedRetryStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class ServiceOverloadedMiddleware implements MiddlewareInterface
{
    private const DELAY_SECONDS = 2;
    // There are also default retry values, so the final number is their multiplication.
    private const MAX_RETRIES = 30;

    private MessageBusInterface $orderBus;

    public function __construct(MessageBusInterface $orderBus)
    {
        $this->orderBus = $orderBus;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (HandlerFailedException $exception) {
            if ($exception->getNestedExceptionOfClass(ServiceOverloadedException::class) !== []) {
                /** @var ServiceOverloadedRetryStamp|null $retryStamp */
                $retryStamp = $envelope->last(ServiceOverloadedRetryStamp::class);
                $retryCount = $retryStamp ? $retryStamp->getRetryCount() : 0;

                if ($retryCount >= self::MAX_RETRIES) {
                    throw $exception;
                }

                $this->orderBus->dispatch($envelope->getMessage(), [
                    new DelayStamp(self::DELAY_SECONDS * 1000),
                    new ServiceOverloadedRetryStamp($retryCount + 1),
                ]);

                return $envelope;
            }

            throw $exception;
        }
    }
}
