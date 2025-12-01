<?php

namespace App\Entity;

class JobStage
{
    private array $updatedOfferStock = [];
    private array $handledStockChange = [];

    public function isOfferStockUpdatedFor(string $article) : bool
    {
        return in_array($article, $this->updatedOfferStock, true);
    }

    public function setOfferStockUpdatedFor(string $article) : void
    {
        $this->updatedOfferStock[] = $article;
    }

    public function isStockChangeHandledFor(string $article) : bool
    {
        return in_array($article, $this->handledStockChange, true);
    }

    public function setStockChangeHandledFor(string $article) : void
    {
        $this->handledStockChange[] = $article;
    }
}
