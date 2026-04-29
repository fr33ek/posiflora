<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Shop;
use App\Entity\TelegramIntegration;
use App\Entity\TelegramSendStatus;
use App\Repository\TelegramSendLogRepository;
use App\Service\OrderService;
use App\Telegram\TelegramClient;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\FakeTelegramClient;

class OrderTelegramNotificationTest extends DatabaseTestCase
{
    public function testCreateOrderWithEnabledIntegrationSendsTelegramAndWritesSentLog(): void
    {
        $fake = new FakeTelegramClient();
        self::getContainer()->set(TelegramClient::class, $fake);

        $shop = $this->persistShop();
        $this->persistIntegration($shop, enabled: true);

        $service = self::getContainer()->get(OrderService::class);
        $result = $service->createOrderAndNotify($shop, 'A-1005', '2490', 'Анна');

        self::assertSame('sent', $result['sendStatus']);
        self::assertSame(1, $fake->calls);

        $sendLogs = self::getContainer()->get(TelegramSendLogRepository::class);
        $log = $sendLogs->findOneBy(['shop' => $shop, 'order' => $result['order']]);

        self::assertNotNull($log);
        self::assertSame(TelegramSendStatus::SENT, $log->getStatus());
    }

    public function testIdempotencySecondCallDoesNotSendAgainAndNoDuplicateLogs(): void
    {
        $fake = new FakeTelegramClient();
        self::getContainer()->set(TelegramClient::class, $fake);

        $shop = $this->persistShop();
        $this->persistIntegration($shop, enabled: true);

        $service = self::getContainer()->get(OrderService::class);
        $first = $service->createOrderAndNotify($shop, 'A-2001', '1000', 'Иван');
        $second = $service->createOrderAndNotify($shop, 'A-2001', '1000', 'Иван');

        self::assertSame('sent', $first['sendStatus']);
        self::assertSame('skipped', $second['sendStatus']);
        self::assertSame(1, $fake->calls);

        $sendLogs = self::getContainer()->get(TelegramSendLogRepository::class);
        self::assertSame(1, $sendLogs->count(['shop' => $shop, 'order' => $first['order']]));
    }

    public function testTelegramErrorWritesFailedLogButOrderStillCreated(): void
    {
        $fake = new FakeTelegramClient(new \RuntimeException('boom'));
        self::getContainer()->set(TelegramClient::class, $fake);

        $shop = $this->persistShop();
        $this->persistIntegration($shop, enabled: true);

        $service = self::getContainer()->get(OrderService::class);
        $result = $service->createOrderAndNotify($shop, 'A-3001', '500', 'Мария');

        self::assertNotNull($result['order']->getId());
        self::assertSame('failed', $result['sendStatus']);
        self::assertSame(1, $fake->calls);

        $sendLogs = self::getContainer()->get(TelegramSendLogRepository::class);
        $log = $sendLogs->findOneBy(['shop' => $shop, 'order' => $result['order']]);

        self::assertNotNull($log);
        self::assertSame(TelegramSendStatus::FAILED, $log->getStatus());
        self::assertNotNull($log->getError());
    }

    private function persistShop(): Shop
    {
        $shop = new Shop('Test shop');
        $this->em->persist($shop);
        $this->em->flush();

        return $shop;
    }

    private function persistIntegration(Shop $shop, bool $enabled): TelegramIntegration
    {
        $integration = new TelegramIntegration($shop, '123:token', '987654321', $enabled);
        $this->em->persist($integration);
        $this->em->flush();

        return $integration;
    }
}

