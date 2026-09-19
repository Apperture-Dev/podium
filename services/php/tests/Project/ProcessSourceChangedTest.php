<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Project\Application\EventHandler\SourceChangedHandler;
use App\Project\Application\Message\SourceChanged;
use App\Project\Domain\Event\ApplicationSourceChanged;
use App\Project\Domain\Event\ServiceDiscovered;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\DeclaredService;
use App\Project\Infrastructure\Manifest\InMemoryPodiumManifestReader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ProcessSourceChangedTest extends KernelTestCase
{
    public function test_known_service_publishes_application_source_changed(): void
    {
        [$handler, $manifestReader, $projects] = $this->boot();

        $project = Project::register('https://github.com/team/repo', 'team-1');
        $project->processSourceChanged('rev-0', 'https://github.com/team/repo', 'github', [
            new DeclaredService('backend', 'php', 'symfony'),
        ]);
        $projects->save($project);

        $manifestReader->willReturn([
            new DeclaredService('backend', 'php', 'symfony'),
        ]);

        $handler(new SourceChanged(
            $project->id()->toString(),
            'rev-1',
            'https://github.com/team/repo',
            'github',
        ));

        $published = $this->envelopesOn('application_source_changed');
        self::assertCount(1, $published);
        self::assertInstanceOf(ApplicationSourceChanged::class, $published[0]->getMessage());
        self::assertSame('backend', $published[0]->getMessage()->serviceName);

        self::assertCount(0, $this->envelopesOn('service_discovered'));
    }

    public function test_new_service_publishes_service_discovered_and_registers_it(): void
    {
        [$handler, $manifestReader, $projects] = $this->boot();

        $project = Project::register('https://github.com/team/repo', 'team-1');
        $projects->save($project);

        $manifestReader->willReturn([
            new DeclaredService('frontend', 'typescript', 'react'),
        ]);

        $handler(new SourceChanged(
            $project->id()->toString(),
            'rev-1',
            'https://github.com/team/repo',
            'github',
        ));

        $published = $this->envelopesOn('service_discovered');
        self::assertCount(1, $published);
        self::assertInstanceOf(ServiceDiscovered::class, $published[0]->getMessage());
        self::assertSame('frontend', $published[0]->getMessage()->serviceName);

        self::assertSame(['frontend'], $projects->get($project->id())->knownServiceNames());
    }

    /** @return array{0: SourceChangedHandler, 1: InMemoryPodiumManifestReader, 2: ProjectRepository} */
    private function boot(): array
    {
        self::bootKernel();
        $container = self::getContainer();

        return [
            $container->get(SourceChangedHandler::class),
            $container->get(InMemoryPodiumManifestReader::class),
            $container->get(ProjectRepository::class),
        ];
    }

    /** @return list<\Symfony\Component\Messenger\Envelope> */
    private function envelopesOn(string $transportName): array
    {
        $transport = self::getContainer()->get('messenger.transport.'.$transportName);
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport->getSent();
    }
}
