<?php

namespace App\Job;

use App\Exception\OrderProcessingException;
use App\Exception\ServiceOverloadedException;
use App\Service\OrderService;
use App\Service\RedisService;
use App\Service\StockService;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RedisException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class UpdateOrderHandler implements MessageHandlerInterface
{
    private StockService $stockService;
    private RedisService $redisService;
    private OrderService $orderService;
    private LoggerInterface $logger;
    public function __construct(
        StockService $stockService,
        RedisService $redisService,
        OrderService $orderService,
        LoggerInterface $logger
    ) {
        $this->stockService = $stockService;
        $this->redisService = $redisService;
        $this->orderService = $orderService;
        $this->logger = $logger;
    }

    /**
     * @throws RedisException
     * @throws OrderProcessingException
     * @throws ServiceOverloadedException
     */
    public function __invoke(UpdateOrderEvent $event): void
    {
        try {
            $order = $this->orderService->getOrder($event->getOrderId(), $event->getSiteCode());
            $event->setOrder($order);
        } catch (InvalidArgumentException $exception) {
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), $exception->getCode());
        }

        $fixedSkuIdsService = $this->stockService->getFixedItemSkuService($order);

        $articles = [];
        foreach ($order->items as $item) {
            $articles[$item->id] = $fixedSkuIdsService->getItemArticle($item);
        }

        $keys = [];
        foreach ($articles as $item => $article) {
            foreach (explode(';', $article) as $simpleArticle) {
                if ($simpleArticle === '') {
                    continue;
                }

                $simpleArticle = explode('*', $simpleArticle)[0];

                if (!$this->redisService->get($simpleArticle)) {
                    $keys[] = $this->redisService->set($simpleArticle, true);

                    $this->logger->debug(sprintf('Article %s consumed', $simpleArticle));
                } else if (in_array($simpleArticle, $keys, true)) {
                    $this->logger->debug(sprintf('Article %s already consumed in that order', $simpleArticle));
                } else {
                    $this->releaseKeys($keys);

                    throw new OrderProcessingException($simpleArticle, $order->id, $item);
                }
            }
        }

        $stage = $event->getStage();

        try {
            $this->stockService->updateStock($order, $stage);
        } catch (\Exception $exception) {
            $this->releaseKeys($keys);

            throw $exception;
        }
    }

    private function releaseKeys(array $keys): void
    {
        foreach ($keys as $key) {
            try {
                $this->redisService->del($key);
                $this->logger->debug(sprintf('Article %s released', $key));
            } catch (\RedisException $e) {
                $this->logger->error(sprintf('Error when releasing article %s: %s', $key, $e->getMessage()));
            }
        }
    }
}
