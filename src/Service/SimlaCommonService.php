<?php

namespace App\Service;

use App\Exception\ServiceOverloadedException;
use Exception;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Client;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;

class SimlaCommonService
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @param Exception $e
     *
     * @return string
     */
    protected function logError(Exception $e): string
    {
        $dbt    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $dbt[1]['function'] ?? null;

        if ($e instanceof ApiExceptionInterface) {
            $message = sprintf(
                'Error from RetailCRM API (status code: %d): %s',
                $e->getStatusCode(),
                $e->getMessage()
            );

            if ($e->getErrorResponse()->errors && count($e->getErrorResponse()->errors) > 0) {
                $message .= PHP_EOL . 'Errors: ' . implode(', ', $e->getErrorResponse()->errors);
            }

        } else {
            $message = $e->getMessage();
        }

        $this->logger->error(
            sprintf(
                '[%s] %s in %s:%s',
                $caller,
                $message,
                $e->getFile(),
                $e->getLine()
            )
        );
        $this->logger->debug($e->getTraceAsString());

        return $message;
    }

    /**
     * @throws ServiceOverloadedException
     */
    protected function checkServiceOverloaded($e): void
    {
        if ($e->getStatusCode() === 503 && $e->getMessage() === 'Service overloaded.') {
            $this->logger->info('Service overloaded. Throwing exception for re-processing.');
            throw new ServiceOverloadedException($e);
        }
    }
}
