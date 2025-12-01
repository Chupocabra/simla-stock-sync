<?php

namespace App\Controller;

use App\Job\UpdateOrderEvent;
use App\Service\ResponseService;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
     * @param Request $request
     * @return JsonResponse
     */
    public function updateByOrder(Request $request): JsonResponse
    {
        $orderId  = $request->request->get('order');
        $siteCode = $request->request->get('site');

        if (!$orderId && !$siteCode) {
            throw new InvalidArgumentException('Order can not be found');
        }

        try {
            $message = new UpdateOrderEvent($orderId, $siteCode);
            $this->orderBus->dispatch($message);

            return $this->responseService->successfulJsonResponse();
        } catch (\Exception $e) {
            return $this->responseService->invalidJsonResponse($e->getMessage());
        }
    }
}
