<?php

namespace App\Controller;

use App\Job\UpdateOrderEvent;
use App\Service\ResponseService;
use RetailCrm\Api\Model\Entity\Orders\Order;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;

class StockController extends AbstractController
{
    /**
     * @var MessageBusInterface $orderBus
     */
    private $orderBus;
    /**
     * @var ResponseService
     */
    private $responseService;

    public function __construct(MessageBusInterface $orderBus, ResponseService $responseService)
    {
        $this->orderBus = $orderBus;
        $this->responseService = $responseService;
    }

    /**
     * @param Order $order
     * @return JsonResponse
     */
    public function updateByOrder(Order $order): JsonResponse
    {
        try {
            $message = new UpdateOrderEvent($order);
            $this->orderBus->dispatch($message);

            return $this->responseService->successfulJsonResponse();
        } catch (\Exception $e) {
            return $this->responseService->invalidJsonResponse($e->getMessage());
        }
    }
}