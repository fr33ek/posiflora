<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Order;
use App\Entity\Shop;
use App\Repository\OrderRepository;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed')]
class SeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShopRepository $shops,
        private readonly OrderRepository $orders,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $shop = $this->shops->find(1);
        if (!$shop instanceof Shop) {
            $shop = new Shop('Demo shop');
            $this->em->persist($shop);
            $this->em->flush();
            $output->writeln(sprintf('Created shop id=%d', $shop->getId()));
        } else {
            $output->writeln(sprintf('Shop id=%d already exists', $shop->getId()));
        }

        $existing = $this->orders->count(['shop' => $shop]);
        if ($existing > 0) {
            $output->writeln(sprintf('Orders already exist (%d), skipping', $existing));
            return Command::SUCCESS;
        }

        $seed = [
            ['A-1001', '1290', 'Анна'],
            ['A-1002', '2490', 'Иван'],
            ['A-1003', '990', 'Мария'],
            ['A-1004', '3990', 'Сергей'],
            ['A-1005', '1490', 'Ольга'],
            ['A-1006', '2190', 'Никита'],
        ];

        foreach ($seed as [$number, $total, $customerName]) {
            $this->em->persist(new Order($shop, $number, $total, $customerName));
        }
        $this->em->flush();

        $output->writeln(sprintf('Seeded %d orders', count($seed)));

        return Command::SUCCESS;
    }
}

