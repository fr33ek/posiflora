<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop;
use App\Entity\TelegramIntegration;
use App\Repository\TelegramIntegrationRepository;
use Doctrine\ORM\EntityManagerInterface;

class TelegramIntegrationService
{
    public function __construct(
        private readonly TelegramIntegrationRepository $integrations,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function upsert(Shop $shop, string $botToken, string $chatId, bool $enabled): TelegramIntegration
    {
        $existing = $this->integrations->findOneByShop($shop);

        if ($existing instanceof TelegramIntegration) {
            $existing
                ->setBotToken($botToken)
                ->setChatId($chatId)
                ->setEnabled($enabled);
            $existing->touch();
            $this->em->flush();

            return $existing;
        }

        $integration = new TelegramIntegration($shop, $botToken, $chatId, $enabled);
        $this->em->persist($integration);
        $this->em->flush();

        return $integration;
    }

    public function getForShop(Shop $shop): ?TelegramIntegration
    {
        return $this->integrations->findOneByShop($shop);
    }
}

