<?php

namespace App\Job;

use App\Exception\OrderProcessingException;
use App\Service\RedisService;
use App\Service\StockService;
use Psr\Log\LoggerInterface;
use RedisException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class UpdateOrderHandler implements MessageHandlerInterface
{
    private StockService $stockService;
    private RedisService $redisService;
    private LoggerInterface $logger;
    public function __construct(StockService $stockService, RedisService $redisService, LoggerInterface $logger)
    {
        $this->stockService = $stockService;
        $this->redisService = $redisService;
        $this->logger = $logger;
    }

    /**
     * @throws RedisException
     * @throws OrderProcessingException
     */
    public function __invoke(UpdateOrderEvent $event): void
    {
        $order = $event->getOrder();

        $fixedSkuIdsService = $this->stockService->getFixedItemSkuService($order);

        $articles = [];
        foreach ($order->items as $item) {
            $articles[$item->id] = $fixedSkuIdsService->getItemArticle($item);
        }

        $keys = [];
        foreach ($articles as $item => $article) {
            foreach (explode(';', $article) as $simpleArticle) {
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

        $this->stockService->updateStock($order);
        $this->releaseKeys($keys);
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
