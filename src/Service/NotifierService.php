<?php

namespace App\Service;

use App\Exception\ServiceOverloadedException;
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
    private const OFFER_SITE = 'fulfillment-simla-com';
    private $notifierClientId;
    private $notifierCustomField10;
    private $notifierCustomField0;
    private array $prevCounts;

    public function __construct(
        Client $client,
        LoggerInterface $logger,
        $notifierClientId,
        $notifierCustomField10,
        $notifierCustomField0
    ) {
        parent::__construct($client, $logger);

        $this->notifierClientId = $notifierClientId;
        $this->notifierCustomField10 = $notifierCustomField10;
        $this->notifierCustomField0 = $notifierCustomField0;
    }

    /**
     * @param Offer[] $inventories
     * @param string $simpleArticle
     */
    public function prepareForStockChange(array $inventories, string $simpleArticle): void
    {
        $count = 0;
        foreach ($inventories as $offer) {
            if ($offer->site === self::OFFER_SITE) {
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
     * @throws ServiceOverloadedException
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

        $prevCount = $this->getPrevCountFor($simpleArticle);

        if ($count < self::COUNT_THRESHOLD && $prevCount >= self::COUNT_THRESHOLD) {
            $this->editCustomerToNotify($this->notifierCustomField10, $simpleArticle);

            $this->logger->info(sprintf(
                '[%s]: count of [%s] less than (%d)',
                __METHOD__,
                $simpleArticle,
                self::COUNT_THRESHOLD)
            );
        } else if ($count < 1 && $prevCount >= 1) {
            $this->editCustomerToNotify($this->notifierCustomField0, $simpleArticle);

            $this->logger->info(sprintf(
                '[%s]: count of [%s] less than (%d)',
                __METHOD__,
                $simpleArticle,
                1)
            );
        }
    }

    private function getPrevCountFor(string $simpleArticle): int
    {
        return $this->prevCounts[$simpleArticle];
    }

    /**
     * @throws ServiceOverloadedException
     */
    private function editCustomerToNotify(string $customField, string $article): void
    {
        $customerEditRequest = new CustomersEditRequest();
        $customerEditRequest->site = self::OFFER_SITE;
        $customerEditRequest->by = ByIdentifier::ID;
        $customerEditRequest->customer = new Customer();
        $customerEditRequest->customer->customFields[$customField] = $article;

        try {
            $this->client->customers->edit($this->notifierClientId, $customerEditRequest);
        } catch (ApiExceptionInterface|ClientExceptionInterface $e) {
            $this->checkServiceOverloaded($e);
            $this->logError($e);
        }
    }
}
