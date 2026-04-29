<?php

declare(strict_types=1);

namespace App\Telegram;

interface TelegramClient
{
    /**
     * @throws \RuntimeException on API/network errors
     */
    public function sendMessage(string $botToken, string $chatId, string $text): void;
}

