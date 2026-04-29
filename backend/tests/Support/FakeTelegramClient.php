<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Telegram\TelegramClient;

class FakeTelegramClient implements TelegramClient
{
    public int $calls = 0;
    public array $messages = [];

    public function __construct(private readonly ?\Throwable $toThrow = null)
    {
    }

    public function sendMessage(string $botToken, string $chatId, string $text): void
    {
        $this->calls++;
        $this->messages[] = [
            'botToken' => $botToken,
            'chatId' => $chatId,
            'text' => $text,
        ];

        if ($this->toThrow) {
            throw $this->toThrow;
        }
    }
}

