<?php

namespace App\Service;

use App\Exception\ServiceOverloadedException;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Entity\Orders\Items\Offer;
use RetailCrm\Api\Model\Entity\Orders\Items\OrderProduct;
use RetailCrm\Api\Model\Filter\Store\OfferFilterType;
use RetailCrm\Api\Model\Request\Store\OffersRequest;

class StoreService extends SimlaCommonService
{
    /**
     * @throws ApiExceptionInterface
     * @throws ServiceOverloadedException
     * @throws ClientExceptionInterface
     */
    public function getItemArticle(FixedItemSkuService $fixedItemSkuService, OrderProduct $item): ?string
    {
        $article = $fixedItemSkuService->getItemArticle($item);

        if (null === $article) {
            $this->logger->debug(sprintf(
                'Article not found in order. Trying to get article from offer %s product',
                $item->offer->id ?? 0
            ));
            $article = $this->getOfferProductArticle($item->offer);
        }

        return $article;
    }

    /**
     * @throws ApiExceptionInterface
     * @throws ServiceOverloadedException
     * @throws ClientExceptionInterface
     */
    private function getOfferProductArticle(Offer $offer): ?string
    {
        if (!$offer->id) {
            $this->logger->debug('Offer id not set');

            return null;
        }

        $offersRequest = new OffersRequest();
        $offersRequest->filter = new OfferFilterType();
        $offersRequest->filter->ids = [$offer->id];

        try {
            $response = $this->client->store->offers($offersRequest);
        } catch (\Exception $e) {
            $this->checkServiceOverloaded($e);
            throw $e;
        }

        $article = $response->offers[0]->product->article;

        return $article ?? null;
    }
}
