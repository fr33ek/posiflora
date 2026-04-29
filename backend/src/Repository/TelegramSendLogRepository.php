<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Shop;
use App\Entity\TelegramSendLog;
use App\Entity\TelegramSendStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TelegramSendLog>
 */
class TelegramSendLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelegramSendLog::class);
    }

    public function existsForShopAndOrder(Shop $shop, Order $order): bool
    {
        return null !== $this->findOneBy(['shop' => $shop, 'order' => $order]);
    }

    public function getStatusStatsForLast7Days(Shop $shop): array
    {
        $from = (new \DateTimeImmutable())->modify('-7 days');

        $qb = $this->createQueryBuilder('l')
            ->select('l.status as status, COUNT(l.id) as cnt')
            ->andWhere('l.shop = :shop')
            ->andWhere('l.sentAt >= :from')
            ->groupBy('l.status')
            ->setParameter('shop', $shop)
            ->setParameter('from', $from);

        $rows = $qb->getQuery()->getArrayResult();

        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $status = $row['status'];
            $cnt = (int) $row['cnt'];
            if ($status instanceof TelegramSendStatus) {
                $status = $status->value;
            }
            if ($status === TelegramSendStatus::SENT->value) {
                $sent = $cnt;
            }
            if ($status === TelegramSendStatus::FAILED->value) {
                $failed = $cnt;
            }
        }

        $lastSentAt = $this->createQueryBuilder('l2')
            ->select('MAX(l2.sentAt)')
            ->andWhere('l2.shop = :shop')
            ->setParameter('shop', $shop)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'sentCount' => $sent,
            'failedCount' => $failed,
            'lastSentAt' => $lastSentAt ? new \DateTimeImmutable((string) $lastSentAt) : null,
        ];
    }
}

