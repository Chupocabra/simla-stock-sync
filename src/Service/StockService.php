<?php

namespace App\Service;

use App\Exception\OutOfStockException;
use App\Exception\StoreNotFoundException;
use Exception;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Client;
use RetailCrm\Api\Exception\Api\AccountDoesNotExistException;
use RetailCrm\Api\Exception\Api\ApiErrorException;
use RetailCrm\Api\Exception\Api\MissingCredentialsException;
use RetailCrm\Api\Exception\Api\MissingParameterException;
use RetailCrm\Api\Exception\Api\ValidationException;
use RetailCrm\Api\Exception\Client\HandlerException;
use RetailCrm\Api\Exception\Client\HttpClientException;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Store\Inventory;
use RetailCrm\Api\Model\Entity\Store\Offer;
use RetailCrm\Api\Model\Filter\Store\InventoryFilterType;
use RetailCrm\Api\Model\Request\Store\InventoriesRequest;
use RetailCrm\Api\Model\Request\Store\InventoriesUploadRequest;
use Symfony\Component\Console\Command\LockableTrait;

class StockService extends SimlaCommonService
{
    // TODO maybe move to env
    private const GENERAL_STORE_CODE = 'central';

    private const PRODUCT_STATUS_SOLD = 'sold';
    private const PRODUCT_STATUS_RETURNED = 'returned';
    private const PRODUCT_STATUS_ERROR = 'out-of-stock';

    private const ORDER_STATUS_READY
        = [
            'preparado',
            'upsell',
            'falta-preparar',
            'falta-preparar-pagados',
            'marketplace',
            self::ORDER_STATUS_RETURNED,
        ];
    private const ORDER_STATUS_RETURNED = 'rehusado-recibido';
    private const ORDER_STATUS_ERROR = 'error-de-descontar-el-stock';

    private $generalSiteCode;
    private $anotherSiteCodes;
    /**
     * @var OrderService
     */
    private $orderService;

    use LockableTrait;

    public function __construct(
        Client $client,
        LoggerInterface $logger,
        OrderService $orderService,
        $generalSiteCode,
        $anotherSiteCodes
    ) {
        parent::__construct($client, $logger);

        $this->generalSiteCode = $generalSiteCode;
        $this->anotherSiteCodes = explode(',', $anotherSiteCodes);
        $this->orderService    = $orderService;
    }

    public function updateStock(Order $order): bool
    {
        if (!$this->isStockChangeAvailable($order)) {
            $this->logger->debug('Skip order due site or status');
            return true;
        }

        $hasChanges    = false;
        $errorMessages = [];
        $supplement    = $order->status === self::ORDER_STATUS_RETURNED;

        $fixedSkuIdsService = $this->getFixedItemSkuService($order);

        foreach ($order->items as $item) {
            $status = $supplement ? self::PRODUCT_STATUS_RETURNED : self::PRODUCT_STATUS_SOLD;
            $article = $fixedSkuIdsService->getItemArticle($item);

            if (
                !$article || $item->status === $status
                || ($supplement && $item->status !== self::PRODUCT_STATUS_SOLD)
            ) {
                $this->logger->debug('Skip item due article or status');
                continue;
            }

            $kitData = $this->isEveryKitItemAvailable($article, $item->quantity, $supplement);

            if (isset($kitData['status'])) {
                $status = $kitData['status'];
            }

            if (isset($kitData['offers'], $kitData['inventories']) && $status !== self::PRODUCT_STATUS_ERROR) {
                $inventories = $kitData['inventories'];
                $offers = $kitData['offers'];

                foreach (explode(';', $article) as $simpleArticle) {
                    $articleAndQuantity = explode('*', $simpleArticle);
                    $simpleArticle = $articleAndQuantity[0];

                    try {
                        $currentInventories = $inventories[$simpleArticle];
                        $currentOffers = $offers[$simpleArticle];

                        if (!empty($this->anotherSiteCodes)) {
                            // Предполагается, что артикулы на главном складе не дублируются, поэтому берем здесь первый оффер
                            $quantity = array_sum(array_map(static function($v): float { return $v->available; }, !empty($currentOffers) ? current($currentOffers)->stores : []));
                            $anotherStoreOffers = $this->setAnotherOffersQuantity($currentInventories, $quantity);
                            foreach ($anotherStoreOffers as $offer) {
                                $currentOffers[] = $offer;
                            }
                        }

                        $this->updateOffersStock($currentOffers);

                        $fixedSkuIdsService->updateFixedSkuIds($item);
                    } catch (Exception | ApiExceptionInterface | ClientExceptionInterface $e) {
                        $this->logError($e);

                        $errorMessages[$simpleArticle] = sprintf(
                            '`%s` error: %s',
                            $simpleArticle,
                            $e->getMessage()
                        );

                        $status = self::PRODUCT_STATUS_ERROR;
                    }

                    $item->status = $status;
                    $hasChanges   = true;
                }
            } else {
                $item->status = self::PRODUCT_STATUS_ERROR;
                $hasChanges   = true;
            }
        }

        if ($hasChanges) {
            $order = $this->prepareOrderForUpdate($order, $errorMessages, $fixedSkuIdsService); // todo refactor

            return $this->orderService->updateOrder($order);
        }

        return true;
    }

    /**
     * @param Order $order
     * @return FixedItemSkuService
     */
    public function getFixedItemSkuService(Order $order): FixedItemSkuService
    {
        $supplement = $order->status === self::ORDER_STATUS_RETURNED;

        return new FixedItemSkuService($supplement, $order, $this->logger);
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    private function isStockChangeAvailable(Order $order): bool
    {
        return ($order->site !== $this->generalSiteCode && in_array($order->status, self::ORDER_STATUS_READY));
    }

    /**
     * @param string $article
     * @param int $quantity
     * @param bool $supplement
     *
     * @return array<string, mixed>
     */
    private function isEveryKitItemAvailable(string $article, int $quantity, bool $supplement): array
    {
        $kitData = [];

        foreach (explode(';', $article) as $simpleArticle) {
            $articleAndQuantity = explode('*', $simpleArticle);
            $simpleArticle = $articleAndQuantity[0];

            $this->logger->debug(
                sprintf(
                    'Article: %s. Quantity: %s%d',
                    $simpleArticle,
                    ($supplement ? '+' : '-'),
                    $quantity * max((float)($articleAndQuantity[1] ?? 1),1)
                )
            );
            try {
                $inventories[$simpleArticle] = $this->getInventoriesByArticle($simpleArticle);
                $offers[$simpleArticle] = $this->getMainOffersQuantity(
                    $inventories[$simpleArticle],
                    $quantity * max((float)($articleAndQuantity[1] ?? 1),1),
                    $supplement
                );
            } catch (Exception | ApiExceptionInterface | ClientExceptionInterface $e) {
                $this->logError($e);

                $status = self::PRODUCT_STATUS_ERROR;
            }
        }

        if (isset($offers)) {
            $kitData['offers'] = $offers;
        }

        if (isset($inventories)) {
            $kitData['inventories'] = $inventories;
        }

        if (isset($status)) {
            $kitData['status'] = $status;
        }

        return $kitData;
    }

    /**
     * @param string $article
     * @return Offer[]
     *
     * @throws AccountDoesNotExistException
     * @throws ApiErrorException
     * @throws ApiExceptionInterface
     * @throws ClientExceptionInterface
     * @throws HandlerException
     * @throws HttpClientException
     * @throws MissingCredentialsException
     * @throws MissingParameterException
     * @throws ValidationException
     */
    private function getInventoriesByArticle(string $article): array
    {
        $filter               = new  InventoryFilterType();
        $filter->offerArticle = [$article];
        $filter->sites = [$this->generalSiteCode];

        if ($this->anotherSiteCodes && !empty(current($this->anotherSiteCodes))) {
            foreach ($this->anotherSiteCodes as $site) {
                $filter->sites[] = $site;
            }
        }

        $filter->details      = '1';

        $inventoriesRequest         = new InventoriesRequest();
        $inventoriesRequest->filter = $filter;

        $inventories = $this->client->store->inventories($inventoriesRequest);
        $this->logger->debug('Inventories: ' . json_encode($inventories, JSON_PRETTY_PRINT));

        return $inventories->offers ?? [];
    }

    /**
     * @param Offer[] $inventories
     * @param float   $itemQuantity
     * @param bool    $supplement
     *
     * @return Offer[]
     * @throws OutOfStockException
     */
    private function getMainOffersQuantity(array $inventories, float $itemQuantity, bool $supplement = false): array
    {
        $offers = [];
        foreach ($inventories as $offerInventory) {
            if ($offerInventory->site !== $this->generalSiteCode) {
                continue;
            }

            $stores = $this->getOffersStoresQuantity($offerInventory, $itemQuantity, $supplement);

            if (count($stores) > 0) {
                $offer             = new Offer();
                $offer->id         = $offerInventory->id;
                $offer->externalId = $offerInventory->externalId;
                $offer->stores     = $stores;

                $offers[] = $offer;
            }
        }

        $this->logger->debug('Offers: ' . json_encode($offers, JSON_PRETTY_PRINT));

        return $offers;
    }

    /**
     * @param Offer[] $inventories
     * @param float $quantity
     *
     * @return Offer[]
     */
    private function setAnotherOffersQuantity(array $inventories, float $quantity): array
    {
        $offers = [];
        foreach ($inventories as $offerInventory) {
            if (!in_array($offerInventory->site, $this->anotherSiteCodes, true)) {
                continue;
            }

            $stores = [];

            foreach ($offerInventory->stores as $store) {
                $storeInventory            = new Inventory();
                $storeInventory->code      = $store->store;
                $storeInventory->available = $quantity;

                $stores[] = $storeInventory;
            }

            if (count($stores) > 0) {
                $offer             = new Offer();
                $offer->id         = $offerInventory->id;
                $offer->externalId = $offerInventory->externalId;
                $offer->stores     = $stores;

                $offers[] = $offer;
            }
        }

        $this->logger->debug('Set offers: ' . json_encode($offers, JSON_PRETTY_PRINT));

        return $offers;
    }

    /**
     * @param Offer $offerInventory
     * @param float $itemQuantity
     * @param bool  $supplement
     *
     * @return Inventory[]
     * @throws OutOfStockException
     */
    private function getOffersStoresQuantity(Offer $offerInventory, float $itemQuantity, bool $supplement = false
    ): array
    {
        $stores = [];

        foreach ($offerInventory->stores as $store) {
            if ($store->store !== self::GENERAL_STORE_CODE) {
                continue;
            }

            $offerQuantity = $store->quantity + ($supplement ? $itemQuantity : -$itemQuantity);

            if ($offerQuantity < 0) {
                $this->logger->error(
                    json_encode([
                        'supplement' => $supplement,
                        'store' => $store,
                        'offer' => $offerInventory,
                        'quantity' => $itemQuantity,
                    ])
                );

                throw new OutOfStockException($store->quantity);
            }

            $storeInventory            = new Inventory();
            $storeInventory->code      = $store->store;
            $storeInventory->available = $offerQuantity;

            $stores[] = $storeInventory;
        }

        return $stores;
    }

    /**
     * @param Offer[] $offers
     *
     * @throws AccountDoesNotExistException
     * @throws ApiErrorException
     * @throws ApiExceptionInterface
     * @throws ClientExceptionInterface
     * @throws HandlerException
     * @throws HttpClientException
     * @throws MissingCredentialsException
     * @throws MissingParameterException
     * @throws StoreNotFoundException
     * @throws ValidationException
     */
    private function updateOffersStock(array $offers): void
    {
        if (count($offers) === 0) {
            throw new StoreNotFoundException();
        }

        if (count($offers) > 250) {
            foreach (array_chunk($offers, 240) as $offersChunk) {
                $this->updateOffersStock($offersChunk);
            }

            return;
        }

        $inventoriesUploadRequest         = new InventoriesUploadRequest();
        $inventoriesUploadRequest->offers = $offers;

        $response = $this->client->store->inventoriesUpload($inventoriesUploadRequest);

        $this->logger->debug('InventoriesUpload: ' . json_encode($response, JSON_PRETTY_PRINT));
    }

    private function prepareOrderForUpdate(Order $orderSample, array $errorMessages, FixedItemSkuService $fixedSkuIdsService): Order // todo refactor
    {
        $order = new Order();

        $order->id = $orderSample->id;
        $order->site = $orderSample->site;
        $order->status = $orderSample->status;
        $order->items = $orderSample->items;

        $order = $this->setOrderStatus($order, $errorMessages);
        $order = $fixedSkuIdsService->setFixedSkuIdsToOrder($order); // todo refactor

        return $order;
    }

    /**
     * @param Order $order
     * @param array $errorMessages
     *
     * @return Order
     */
    private function setOrderStatus(
        Order $order,
        array $errorMessages
    ): Order
    {
        if (count($errorMessages) > 0) {
            $order->status        = self::ORDER_STATUS_ERROR;
            $order->statusComment = implode("\n", $errorMessages);
        }

        return $order;
    }
}
