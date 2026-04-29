<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TelegramSendLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TelegramSendLogRepository::class)]
#[ORM\Table(name: 'telegram_send_log')]
#[ORM\UniqueConstraint(name: 'uniq_telegram_send_log_shop_order', columns: ['shop_id', 'order_id'])]
class TelegramSendLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Shop $shop;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(enumType: TelegramSendStatus::class)]
    private TelegramSendStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    #[ORM\Column(name: 'sent_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $sentAt;

    public function __construct(Shop $shop, Order $order, string $message, TelegramSendStatus $status, ?string $error)
    {
        $this->shop = $shop;
        $this->order = $order;
        $this->message = $message;
        $this->status = $status;
        $this->error = $error;
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getStatus(): TelegramSendStatus
    {
        return $this->status;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}

