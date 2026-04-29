<?php

declare(strict_types=1);

namespace App\Telegram;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpTelegramClient implements TelegramClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly float $timeoutSeconds = 5.0,
    ) {
    }

    public function sendMessage(string $botToken, string $chatId, string $text): void
    {
        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $botToken);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'timeout' => $this->timeoutSeconds,
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                ],
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Telegram request failed: '.$e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $body = $response->getContent(false);
            throw new \RuntimeException(sprintf('Telegram API error: HTTP %d: %s', $statusCode, $body));
        }
    }
}

