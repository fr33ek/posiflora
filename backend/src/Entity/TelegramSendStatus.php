<?php

declare(strict_types=1);

namespace App\Entity;

enum TelegramSendStatus: string
{
    case SENT = 'SENT';
    case FAILED = 'FAILED';
}

