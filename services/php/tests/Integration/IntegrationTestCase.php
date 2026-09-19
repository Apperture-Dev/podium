<?php

declare(strict_types=1);

namespace Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base class for integration tests.
 *
 * Integration tests should:
 * - Test interaction between multiple components
 * - Access real database (transactions rolled back by DAMA doctrine-test-bundle)
 * - Test repository implementations
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager = null;
    }

    protected function getService(string $serviceId): object
    {
        return static::getContainer()->get($serviceId);
    }

    protected function clearEntityManager(): void
    {
        $this->entityManager->clear();
    }
}
