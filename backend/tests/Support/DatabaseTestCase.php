<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();

        if ($metadata !== []) {
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->em->close();
        }

        parent::tearDown();
    }
}

