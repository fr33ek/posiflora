<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TelegramSendLogRepository;
use App\Service\OrderService;
use App\Service\ShopResolver;
use App\Service\TelegramIntegrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class GrowthTelegramController extends AbstractController
{
    public function __construct(
        private readonly ShopResolver $shopResolver,
        private readonly TelegramIntegrationService $integrationService,
        private readonly OrderService $orderService,
        private readonly TelegramSendLogRepository $sendLogs,
    ) {
    }

    #[Route('/shops/{shopId}/telegram/connect', name: 'shops_telegram_connect', methods: ['POST'])]
    public function connect(int $shopId, Request $request): JsonResponse
    {
        $payload = $request->toArray();

        $botToken = (string) ($payload['botToken'] ?? '');
        $chatId = (string) ($payload['chatId'] ?? '');
        $enabled = (bool) ($payload['enabled'] ?? false);

        if (trim($botToken) === '' || trim($chatId) === '') {
            return $this->json(['error' => 'botToken and chatId are required'], 422);
        }

        $shop = $this->shopResolver->getShop($shopId);
        $integration = $this->integrationService->upsert($shop, $botToken, $chatId, $enabled);

        return $this->json([
            'id' => $integration->getId(),
            'shopId' => $shop->getId(),
            'botToken' => $integration->getBotToken(),
            'chatId' => $integration->getChatId(),
            'enabled' => $integration->isEnabled(),
            'createdAt' => $integration->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $integration->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/shops/{shopId}/orders', name: 'shops_orders_create', methods: ['POST'])]
    public function createOrder(int $shopId, Request $request): JsonResponse
    {
        $payload = $request->toArray();

        $number = (string) ($payload['number'] ?? '');
        $total = (string) ($payload['total'] ?? '');
        $customerName = (string) ($payload['customerName'] ?? '');

        if (trim($number) === '' || trim($total) === '' || trim($customerName) === '') {
            return $this->json(['error' => 'number, total, customerName are required'], 422);
        }

        $normalizedTotal = str_replace(',', '.', trim($total));
        if (!is_numeric($normalizedTotal)) {
            return $this->json(['error' => 'total must be a valid number'], 422);
        }

        $shop = $this->shopResolver->getShop($shopId);
        $result = $this->orderService->createOrderAndNotify($shop, $number, $normalizedTotal, $customerName);
        $order = $result['order'];

        return $this->json([
            'order' => [
                'id' => $order->getId(),
                'shopId' => $shop->getId(),
                'number' => $order->getNumber(),
                'total' => $order->getTotal(),
                'customerName' => $order->getCustomerName(),
                'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            'sendStatus' => $result['sendStatus'],
            'skipReason' => $result['skipReason'],
        ]);
    }

    #[Route('/shops/{shopId}/telegram/status', name: 'shops_telegram_status', methods: ['GET'])]
    public function status(int $shopId): JsonResponse
    {
        $shop = $this->shopResolver->getShop($shopId);
        $integration = $this->integrationService->getForShop($shop);

        $stats = $this->sendLogs->getStatusStatsForLast7Days($shop);
        $lastSentAt = $stats['lastSentAt'];

        return $this->json([
            'enabled' => $integration?->isEnabled() ?? false,
            'chatId' => $integration ? $this->maskChatId($integration->getChatId()) : null,
            'lastSentAt' => $lastSentAt ? $lastSentAt->format(\DateTimeInterface::ATOM) : null,
            'sentCount' => $stats['sentCount'],
            'failedCount' => $stats['failedCount'],
        ]);
    }

    private function maskChatId(string $chatId): string
    {
        $trimmed = trim($chatId);
        $len = mb_strlen($trimmed);
        if ($len <= 4) {
            return $trimmed;
        }

        return str_repeat('*', $len - 4).mb_substr($trimmed, -4);
    }
}

