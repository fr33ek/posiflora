<?php

declare(strict_types=1);

namespace App\Telegram;

class RouterTelegramClient implements TelegramClient
{
    public function __construct(
        private readonly HttpTelegramClient $http,
        private readonly MockTelegramClient $mock,
        private readonly bool $useRealTelegram = false,
    ) {
    }

    public function sendMessage(string $botToken, string $chatId, string $text): void
    {
        if ($this->useRealTelegram) {
            $this->http->sendMessage($botToken, $chatId, $text);
            return;
        }

        $this->mock->sendMessage($botToken, $chatId, $text);
    }
}

