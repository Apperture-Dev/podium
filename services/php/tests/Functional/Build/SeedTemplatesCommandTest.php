<?php

declare(strict_types=1);

namespace Tests\Functional\Build;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedTemplatesCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $this->commandTester = new CommandTester($application->find('app:build:seed-templates'));
    }

    public function testSeedsTheNodejsNestjsTemplate(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('nodejs/nestjs', $this->commandTester->getDisplay());
    }
}
