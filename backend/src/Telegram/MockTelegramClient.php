<?php

declare(strict_types=1);

namespace App\Telegram;

class MockTelegramClient implements TelegramClient
{
    public function __construct(private readonly bool $forceFail = false)
    {
    }

    public function sendMessage(string $botToken, string $chatId, string $text): void
    {
        if ($this->forceFail) {
            throw new \RuntimeException('Mock telegram failure (forced).');
        }
    }
}

