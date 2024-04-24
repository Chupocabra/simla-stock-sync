<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use RetailCrm\Api\Model\Entity\Orders\Items\OrderProduct;
use RetailCrm\Api\Model\Entity\Orders\Order;

class FixedItemSkuService
{
    public const FIXED_ITEMS_WITH_ID_CODE = 'fixed_items_with_id';
    public const FIXED_ITEMS_WITH_ID_SEPARATOR_ITEM = ';';
    public const FIXED_ITEMS_WITH_ID_SEPARATOR_VALUE = ':';
    /**
     * @var bool
     */
    private $supplement;

    /**
     * @var array
     */
    private $fixedSkuIds;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(bool $supplement, Order $order, LoggerInterface $logger)
    {
        $this->supplement = $supplement;
        $this->logger = $logger;

        self::getFixedSkuIdsFromOrder($order);
    }

    private function getFixedSkuIdsFromOrder(Order $order): void
    {
        $this->fixedSkuIds = [];
        $fieldRawValue     = $order->customFields[self::FIXED_ITEMS_WITH_ID_CODE] ?? null;

        if(empty($fieldRawValue)) {
            return;
        }

        $arrayWithRawItems = explode(self::FIXED_ITEMS_WITH_ID_SEPARATOR_ITEM, $fieldRawValue);

        foreach ($arrayWithRawItems as $rawItem) {
            $fixedSkuId = explode(self::FIXED_ITEMS_WITH_ID_SEPARATOR_VALUE, $rawItem);

            if(count($fixedSkuId) !== 2) {
                continue;
            }

            $this->fixedSkuIds[$fixedSkuId[0]] = $fixedSkuId[1];
        }

        $this->logger->debug(sprintf('[%s] %s to %s', __METHOD__, $fieldRawValue, json_encode($this->fixedSkuIds)));
    }

    public function getItemArticle(OrderProduct $item): ?string
    {
        if ($this->supplement && isset($this->fixedSkuIds[strval($item->id)])) {
            $this->logger->debug(
                sprintf('[%s] %d to %s', __METHOD__, $item->id, $this->fixedSkuIds[strval($item->id)])
            );

            return $this->fixedSkuIds[strval($item->id)];
        }

        return $item->offer->article ?? null;
    }

    public function updateFixedSkuIds(OrderProduct $item): void
    {
        if ($this->supplement) {
            return;
        }

        $this->logger->debug(sprintf('[%s] %d to %s', __METHOD__, $item->id, $item->offer->article));

        $this->fixedSkuIds[strval($item->id)] = $item->offer->article;
    }

    public function setFixedSkuIdsToOrder(Order $order): Order
    {
        if ($this->supplement) {
            return $order;
        }

        $itemsFormatted = [];
        foreach ($this->fixedSkuIds as $id => $sku) {
            $itemsFormatted[] = sprintf("%s%s%s", $id, self::FIXED_ITEMS_WITH_ID_SEPARATOR_VALUE, $sku);
        }

        $this->logger->debug(
            sprintf(
                '[%s] %s to %s', __METHOD__, json_encode($this->fixedSkuIds), implode(
                    self::FIXED_ITEMS_WITH_ID_SEPARATOR_ITEM, $itemsFormatted
                )
            )
        );

        $order->customFields[self::FIXED_ITEMS_WITH_ID_CODE] = implode(
            self::FIXED_ITEMS_WITH_ID_SEPARATOR_ITEM, $itemsFormatted
        );

        return $order;
    }
}
