<?php

namespace App\Factory;

use Psr\Log\LoggerInterface;
use RetailCrm\Api\Builder\ClientBuilder;
use RetailCrm\Api\Builder\FormEncoderBuilder;
use RetailCrm\Api\Client;
use RetailCrm\Api\Exception\Client\BuilderException;
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Handler\Request\HeaderAuthenticatorHandler;

class SimlaApiFactory
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param $crmUrl
     * @param $crmApiKey
     *
     * @return Client
     * @throws BuilderException
     */
    public function createClient($crmUrl, $crmApiKey): Client
    {
        if ($crmUrl === null || $crmApiKey === null) {
            throw new BuilderException('Set url and api key');
        }

//        $this->client = SimpleClientFactory::createClient($shop->getCrmUrl(),$shop->getCrmApiKey());
        $clientBuilder = new ClientBuilder();

        return $clientBuilder
            ->setApiUrl($crmUrl)
            ->setAuthenticatorHandler(new HeaderAuthenticatorHandler($crmApiKey))
            ->setDebugLogger($this->logger)
            ->setFormEncoder((new FormEncoderBuilder())->build())
            ->build();
    }
}