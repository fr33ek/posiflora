<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\UniqueConstraint(name: 'uniq_orders_shop_number', columns: ['shop_id', 'number'])]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Shop $shop;

    #[ORM\Column(type: 'text')]
    private string $number;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $total;

    #[ORM\Column(name: 'customer_name', type: 'text')]
    private string $customerName;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Shop $shop, string $number, string $total, string $customerName)
    {
        $this->shop = $shop;
        $this->number = $number;
        $this->total = $total;
        $this->customerName = $customerName;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

