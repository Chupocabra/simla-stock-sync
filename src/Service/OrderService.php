<?php

namespace App\Service;

use InvalidArgumentException;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Request\BySiteRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersEditRequest;

class OrderService extends SimlaCommonService
{
    public function getOrder(string $orderId, string $siteCode): Order
    {
        $this->logger->debug('Getting order info', ['id' => $orderId, 'site' => $siteCode]);

        try {
            $orderResponse = $this->client->orders->get($orderId, new BySiteRequest(ByIdentifier::ID, $siteCode));

//            $this->logger->debug('Get Order: ' . json_encode($orderResponse));

            if (!$orderResponse->success) {
                throw new InvalidArgumentException('Order not found');
            }
        } catch (\Exception | ApiExceptionInterface | ClientExceptionInterface $e) {
            $message = $this->logError($e);

            throw new InvalidArgumentException($message);
        }

        return $orderResponse->order;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function updateOrder(Order $order): bool
    {
        $request        = new OrdersEditRequest();
        $request->order = $order;
        $request->site  = $order->site;
        $request->by    = ByIdentifier::ID;

        try {
            $response = $this->client->orders->edit(strval($request->order->id), $request);

//            $this->logger->debug('Update order response: ' . json_encode($response));
        } catch (\Exception | ApiExceptionInterface | ClientExceptionInterface $e) {
            $this->logError($e);

            return false;
        }

        return true;
    }
}
