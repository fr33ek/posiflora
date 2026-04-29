<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shop;
use App\Entity\TelegramIntegration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TelegramIntegration>
 */
class TelegramIntegrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelegramIntegration::class);
    }

    public function findOneByShop(Shop $shop): ?TelegramIntegration
    {
        return $this->findOneBy(['shop' => $shop]);
    }
}

