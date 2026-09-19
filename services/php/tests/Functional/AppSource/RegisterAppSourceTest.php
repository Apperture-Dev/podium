<?php

declare(strict_types=1);

namespace Tests\Functional\AppSource;

use App\AppSource\Application\ApplicationService;
use App\AppSource\Application\EventHandler\ProjectRegisteredHandler;
use App\AppSource\Domain\Port\AppSourceRepository;
use App\AppSource\Domain\ValueObject\AppSourceId;
use App\Project\Domain\Event\ProjectRegistered;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RegisterAppSourceTest extends KernelTestCase
{
    private ApplicationService $applicationService;
    private ProjectRegisteredHandler $handler;
    private AppSourceRepository $appSources;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        $this->applicationService = $container->get(ApplicationService::class);
        $this->handler = $container->get(ProjectRegisteredHandler::class);
        $this->appSources = $container->get(AppSourceRepository::class);
    }

    public function testRegisteringAnAppSourceStartsWithoutARevision(): void
    {
        $appSourceId = $this->applicationService->registerAppSource('project-1', 'https://github.com/team/repo');

        $appSource = $this->appSources->get(AppSourceId::fromString($appSourceId));
        self::assertSame('project-1', $appSource->projectId());
        self::assertSame('https://github.com/team/repo', $appSource->repositoryUrl());
        self::assertNull($appSource->revision());
    }

    public function testProjectRegisteredEventMakesAppSourceReact(): void
    {
        // Smoke test de wiring: el handler real (el que Messenger invoca al
        // consumir el evento de Redis) desempaqueta el mensaje y delega sin
        // lanzar. La lógica de creación en sí ya está cubierta arriba.
        ($this->handler)(new ProjectRegistered('project-2', 'https://github.com/team/repo2', 'team-1'));

        $this->addToAssertionCount(1);
    }
}
