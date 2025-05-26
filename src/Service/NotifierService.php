<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use RetailCrm\Api\Client;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Entity\Customers\Customer;
use RetailCrm\Api\Model\Entity\Store\Offer;
use RetailCrm\Api\Model\Request\Customers\CustomersEditRequest;

class NotifierService extends SimlaCommonService
{
    private const COUNT_THRESHOLD = 10;
    private $notifierClientId;
    private $notifierCustomField;
    private array $prevCounts;

    public function __construct(
        Client $client,
        LoggerInterface $logger,
        $notifierClientId,
        $notifierCustomField
    ) {
        parent::__construct($client, $logger);

        $this->notifierClientId = $notifierClientId;
        $this->notifierCustomField = $notifierCustomField;
    }

    /**
     * @param Offer[] $inventories
     * @param string $simpleArticle
     */
    public function prepareForStockChange(array $inventories, string $simpleArticle): void
    {
        $count = 0;
        foreach ($inventories as $offer) {
            if ($offer->site === StockService::GENERAL_STORE_CODE) {
                $count = $offer->quantity;
                break;
            }
        }

        $this->prevCounts[$simpleArticle] = $count;
        $this->logger->debug(
            sprintf('[%s]: [%s] - (%d)', __METHOD__, $simpleArticle, $this->prevCounts[$simpleArticle]),
            ['prevCounts' => $this->prevCounts]
        );
    }

    /**
     * @param Offer[] $offers
     * @param string $simpleArticle
     * @return void
     */
    public function handleStockChange(array $offers, string $simpleArticle): void
    {
        $count = 0;
        $offer = current($offers);
        foreach ($offer->stores as $store) {
            if ($store->code === StockService::GENERAL_STORE_CODE) {
                $count = $store->available;
                break;
            }
        }

        $this->logger->debug(sprintf('[%s]: [%s] - (%d)',__METHOD__, $simpleArticle, $count));

        if ($count < self::COUNT_THRESHOLD && $this->getPrevCountFor($simpleArticle) >= self::COUNT_THRESHOLD) {
            try {
                $customerEditRequest = new CustomersEditRequest();
                $customerEditRequest->site = StockService::GENERAL_STORE_CODE;
                $customerEditRequest->by = ByIdentifier::ID;
                $customerEditRequest->customer = new Customer();
                $customerEditRequest->customer->customFields[$this->notifierCustomField] = $simpleArticle;

                $this->client->customers->edit($this->notifierClientId, $customerEditRequest);
            } catch (ApiExceptionInterface|ClientExceptionInterface $e) {
                $this->logError($e);
            }

            $this->logger->info(sprintf(
                '[%s]: count of [%s] less than (%d)',
                __METHOD__,
                $simpleArticle,
                self::COUNT_THRESHOLD)
            );
        }
    }

    private function getPrevCountFor(string $simpleArticle): int
    {
        return $this->prevCounts[$simpleArticle];
    }
}