<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\Shop;
use App\Entity\TelegramSendLog;
use App\Entity\TelegramSendStatus;
use App\Repository\OrderRepository;
use App\Repository\TelegramIntegrationRepository;
use App\Repository\TelegramSendLogRepository;
use App\Telegram\TelegramClient;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orders,
        private readonly TelegramIntegrationRepository $integrations,
        private readonly TelegramSendLogRepository $sendLogs,
        private readonly TelegramClient $telegram,
    ) {
    }

    /**
     * @return array{
     *   order: Order,
     *   sendStatus: 'sent'|'failed'|'skipped',
     *   skipReason: 'duplicate_order'|'integration_disabled'|'already_notified'|null
     * }
     */
    public function createOrderAndNotify(Shop $shop, string $number, string $total, string $customerName): array
    {
        $order = $this->orders->findOneByShopAndNumber($shop, $number);
        $orderAlreadyExists = $order instanceof Order;

        if (!$orderAlreadyExists) {
            $order = new Order($shop, $number, $total, $customerName);
            $this->em->persist($order);
            try {
                $this->em->flush();
            } catch (UniqueConstraintViolationException $e) {
                $this->em->clear();
                $order = $this->orders->findOneByShopAndNumber($shop, $number);
                if (!$order instanceof Order) {
                    throw $e;
                }
            }
        }

        if ($orderAlreadyExists) {
            return ['order' => $order, 'sendStatus' => 'skipped', 'skipReason' => 'duplicate_order'];
        }

        $integration = $this->integrations->findOneByShop($shop);
        if (!$integration || !$integration->isEnabled()) {
            return ['order' => $order, 'sendStatus' => 'skipped', 'skipReason' => 'integration_disabled'];
        }

        if ($this->sendLogs->existsForShopAndOrder($shop, $order)) {
            return ['order' => $order, 'sendStatus' => 'skipped', 'skipReason' => 'already_notified'];
        }

        $message = sprintf(
            'Новый заказ %s на сумму %s ₽, клиент %s',
            $order->getNumber(),
            $this->formatRubles($order->getTotal()),
            $order->getCustomerName(),
        );

        try {
            $this->telegram->sendMessage($integration->getBotToken(), $integration->getChatId(), $message);
            $this->persistSendLog($shop, $order, $message, TelegramSendStatus::SENT, null);
            return ['order' => $order, 'sendStatus' => 'sent', 'skipReason' => null];
        } catch (\Throwable $e) {
            $this->persistSendLog($shop, $order, $message, TelegramSendStatus::FAILED, $e->getMessage());
            return ['order' => $order, 'sendStatus' => 'failed', 'skipReason' => null];
        }
    }

    private function persistSendLog(Shop $shop, Order $order, string $message, TelegramSendStatus $status, ?string $error): void
    {
        if ($this->sendLogs->existsForShopAndOrder($shop, $order)) {
            return;
        }

        $log = new TelegramSendLog($shop, $order, $message, $status, $error);
        $this->em->persist($log);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // idempotency: someone already inserted the log
        }
    }

    private function formatRubles(string $total): string
    {
        $normalized = str_replace(',', '.', trim($total));
        if (!is_numeric($normalized)) {
            return $total;
        }

        $float = (float) $normalized;
        if (abs($float - round($float)) < 0.00001) {
            return (string) ((int) round($float));
        }

        return rtrim(rtrim(number_format($float, 2, '.', ''), '0'), '.');
    }
}

