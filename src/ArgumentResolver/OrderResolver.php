<?php

namespace App\ArgumentResolver;

use App\Service\OrderService;
use Generator;
use RetailCrm\Api\Model\Entity\Orders\Order;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class OrderResolver implements ArgumentValueResolverInterface
{
    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        if (Order::class === $argument->getType()) {
            $orderId  = $request->request->get('order');
            $siteCode = $request->request->get('site');

            if (null !== $orderId && null !== $siteCode) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Request          $request
     * @param ArgumentMetadata $argument
     *
     * @return Generator
     */
    public function resolve(Request $request, ArgumentMetadata $argument): Generator
    {
        $orderId  = $request->request->get('order');
        $siteCode = $request->request->get('site');

        yield $this->orderService->getOrder($orderId, $siteCode);
    }
}
