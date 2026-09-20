<?php

declare(strict_types=1);

namespace Tests\Unit\Deploy\Domain;

use App\Deploy\Domain\DeployAttempt;
use App\Deploy\Domain\DeployStatus;
use App\Deploy\Domain\Event\DeployAttemptRequested;
use App\Deploy\Domain\Event\DeployFailed;
use App\Deploy\Domain\Event\DeploySucceeded;
use Tests\Unit\UnitTestCase;

final class DeployAttemptTest extends UnitTestCase
{
    private DeployAttempt $deployAttempt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deployAttempt = DeployAttempt::request(
            'team-1',
            'backend',
            'project-1',
            'abc12345',
            'v1',
            'registry/backend:rev-1',
            3000,
            ['API_URL' => 'https://x'],
            [],
        );
        $this->deployAttempt->releaseEvents();
    }

    public function testRequestStartsInPendingAndPublishesDeployAttemptRequested(): void
    {
        $deployAttempt = DeployAttempt::request('team-1', 'backend', 'project-1', 'abc12345', 'v1', 'registry/backend:rev-1', 3000, ['API_URL' => 'https://x'], []);

        $events = $deployAttempt->releaseEvents();

        self::assertSame(DeployStatus::Pending, $deployAttempt->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(DeployAttemptRequested::class, $events[0]);
        self::assertSame($deployAttempt->id()->toString(), $events[0]->deployAttemptId);
        self::assertSame('abc12345', $events[0]->hash);
        self::assertSame('backend', $events[0]->serviceName);
        self::assertSame('registry/backend:rev-1', $events[0]->image);
        self::assertSame(3000, $events[0]->port);
        self::assertSame(['API_URL' => 'https://x'], $events[0]->envVars);
    }

    public function testCompleteDeployAttemptMovesToSucceededAndPublishesDeploySucceeded(): void
    {
        $events = $this->deployAttempt->completeDeployAttempt();

        self::assertSame(DeployStatus::Succeeded, $this->deployAttempt->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(DeploySucceeded::class, $events[0]);
        self::assertSame('backend', $events[0]->serviceName);
    }

    public function testFailDeployAttemptMovesToFailedAndPublishesDeployFailed(): void
    {
        $events = $this->deployAttempt->failDeployAttempt('reintentos de health-check agotados', 3);

        self::assertSame(DeployStatus::Failed, $this->deployAttempt->status());
        self::assertSame('reintentos de health-check agotados', $this->deployAttempt->errorMessage());
        self::assertSame(3, $this->deployAttempt->retryCount());
        self::assertCount(1, $events);
        self::assertInstanceOf(DeployFailed::class, $events[0]);
        self::assertSame(3, $events[0]->retryCount);
    }

    public function testFailDeployAttemptAcceptsNoRetryCount(): void
    {
        $this->deployAttempt->failDeployAttempt('error desconocido', null);

        self::assertNull($this->deployAttempt->retryCount());
    }
}
