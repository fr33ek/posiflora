<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TelegramIntegrationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TelegramIntegrationRepository::class)]
#[ORM\Table(name: 'telegram_integrations')]
#[ORM\UniqueConstraint(name: 'uniq_telegram_integrations_shop_id', columns: ['shop_id'])]
class TelegramIntegration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Shop $shop;

    #[ORM\Column(name: 'bot_token', type: 'text')]
    private string $botToken;

    #[ORM\Column(name: 'chat_id', type: 'text')]
    private string $chatId;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Shop $shop, string $botToken, string $chatId, bool $enabled)
    {
        $this->shop = $shop;
        $this->botToken = $botToken;
        $this->chatId = $chatId;
        $this->enabled = $enabled;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function getBotToken(): string
    {
        return $this->botToken;
    }

    public function setBotToken(string $botToken): self
    {
        $this->botToken = $botToken;

        return $this;
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }

    public function setChatId(string $chatId): self
    {
        $this->chatId = $chatId;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

