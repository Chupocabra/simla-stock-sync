<?php

namespace App\Job;

use App\Entity\JobStage;

class UpdateOrderEvent
{
    private string $orderId;
    private string $siteCode;
    private JobStage $stage;

    public function __construct(string $orderId, string $siteCode)
    {
        $this->orderId = $orderId;
        $this->siteCode = $siteCode;
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

    /**
     * @return JobStage
     */
    public function getStage(): JobStage
    {
        return $this->stage;
    }
}
