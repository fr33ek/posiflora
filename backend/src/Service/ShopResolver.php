<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop;
use App\Repository\ShopRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShopResolver
{
    public function __construct(private readonly ShopRepository $shops)
    {
    }

    public function getShop(int $shopId): Shop
    {
        $shop = $this->shops->find($shopId);
        if (!$shop instanceof Shop) {
            throw new NotFoundHttpException('Shop not found.');
        }

        return $shop;
    }
}

