<?php

namespace App\Job;

use App\Entity\JobStage;
use RetailCrm\Api\Model\Entity\Orders\Order;

class UpdateOrderEvent
{
    private string $orderId;
    private string $siteCode;
    private Order $order;
    private JobStage $stage;

    public function __construct(string $orderId, string $siteCode)
    {
        $this->orderId = $orderId;
        $this->siteCode = $siteCode;
        $this->order = new Order();
        $this->stage = new JobStage();
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getSiteCode(): string
    {
        return $this->siteCode;
    }

    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    /**
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setStage(?JobStage $stage): void
    {
        $this->stage = $stage;
    }

    /**
     * @return JobStage
     */
    public function getStage(): JobStage
    {
        return $this->stage;
    }
}
