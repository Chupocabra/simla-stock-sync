<?php

namespace App\Factory;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Builder\ClientBuilder;
use RetailCrm\Api\Builder\FormEncoderBuilder;
use RetailCrm\Api\Client;
use RetailCrm\Api\Exception\Client\BuilderException;
use RetailCrm\Api\Handler\Request\HeaderAuthenticatorHandler;
use Symfony\Component\HttpClient\Psr18Client;

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

        // $this->client = SimpleClientFactory::createClient($shop->getCrmUrl(),$shop->getCrmApiKey());
        $psr17Factory = new Psr17Factory();
        $psr18Client = new Psr18Client(null, $psr17Factory, $psr17Factory);
        $clientBuilder = new ClientBuilder();

        return $clientBuilder
            ->setApiUrl($crmUrl)
            ->setAuthenticatorHandler(new HeaderAuthenticatorHandler($crmApiKey))
            ->setDebugLogger($this->logger)
            ->setFormEncoder((new FormEncoderBuilder())->build())
            // Force PSR-18 client to avoid deprecated Httplug adapter
            ->setHttpClient($psr18Client)
            ->setRequestFactory($psr17Factory)
            ->setStreamFactory($psr17Factory)
            ->setUriFactory($psr17Factory)
            ->build();
    }
}