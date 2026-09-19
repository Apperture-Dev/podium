<?php

declare(strict_types=1);

namespace Tests\Functional\Project;

use App\AppSource\Domain\Event\SourceChanged;
use App\Project\Application\EventHandler\SourceChangedHandler;
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
    private SourceChangedHandler $handler;
    private InMemoryPodiumManifestReader $manifestReader;
    private ProjectRepository $projects;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        $this->handler = $container->get(SourceChangedHandler::class);
        $this->manifestReader = $container->get(InMemoryPodiumManifestReader::class);
        $this->projects = $container->get(ProjectRepository::class);
    }

    public function testKnownServicePublishesApplicationSourceChanged(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1');
        $project->processSourceChanged('rev-0', 'https://github.com/team/repo', 'github', [
            new DeclaredService('backend', 'php', 'symfony'),
        ]);
        $this->projects->save($project);

        $this->manifestReader->willReturn([
            new DeclaredService('backend', 'php', 'symfony'),
        ]);

        ($this->handler)(new SourceChanged(
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

    public function testNewServicePublishesServiceDiscoveredAndRegistersIt(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1');
        $this->projects->save($project);

        $this->manifestReader->willReturn([
            new DeclaredService('frontend', 'typescript', 'react'),
        ]);

        ($this->handler)(new SourceChanged(
            $project->id()->toString(),
            'rev-1',
            'https://github.com/team/repo',
            'github',
        ));

        $published = $this->envelopesOn('service_discovered');
        self::assertCount(1, $published);
        self::assertInstanceOf(ServiceDiscovered::class, $published[0]->getMessage());
        self::assertSame('frontend', $published[0]->getMessage()->serviceName);

        self::assertSame(['frontend'], $this->projects->get($project->id())->knownServiceNames());
    }

    /** @return list<\Symfony\Component\Messenger\Envelope> */
    private function envelopesOn(string $transportName): array
    {
        $transport = self::getContainer()->get('messenger.transport.'.$transportName);
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport->getSent();
    }
}
