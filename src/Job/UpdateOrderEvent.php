<?php

namespace App\Job;

use RetailCrm\Api\Model\Entity\Orders\Order;

class UpdateOrderEvent
{
    private Order $order;
    public function __construct(Order $order)
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
}
