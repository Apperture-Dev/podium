<?php

declare(strict_types=1);

namespace Tests\Functional\AppSource;

use App\AppSource\Domain\AppSource;
use App\AppSource\Domain\Event\SourceChanged;
use App\AppSource\Domain\Port\AppSourceRepository;
use App\AppSource\Infrastructure\GitHub\InMemoryLatestCommitChecker;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class PollAppSourcesCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private InMemoryLatestCommitChecker $latestCommitChecker;
    private AppSourceRepository $appSources;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $this->commandTester = new CommandTester($application->find('app:appsource:poll'));

        $container = self::getContainer();
        $this->latestCommitChecker = $container->get(InMemoryLatestCommitChecker::class);
        $this->appSources = $container->get(AppSourceRepository::class);
    }

    public function testPublishesSourceChangedForTheFirstRevisionEverSeen(): void
    {
        $appSource = AppSource::register('project-1', 'https://github.com/team/repo', 'github');
        $this->appSources->save($appSource);
        $this->latestCommitChecker->willReturn('https://github.com/team/repo', 'rev-1');

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        $published = $this->envelopesOn('source_changed');
        self::assertCount(1, $published);
        self::assertInstanceOf(SourceChanged::class, $published[0]->getMessage());
        self::assertSame('rev-1', $published[0]->getMessage()->revision);
        self::assertSame('rev-1', $this->appSources->get($appSource->id())->revision());
    }

    public function testDoesNotPublishWhenTheRevisionHasNotChanged(): void
    {
        $appSource = AppSource::register('project-2', 'https://github.com/team/repo2', 'github');
        $appSource->recordRevision('rev-1');
        $this->appSources->save($appSource);
        $this->latestCommitChecker->willReturn('https://github.com/team/repo2', 'rev-1');

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertCount(0, $this->envelopesOn('source_changed'));
    }

    public function testPublishesSourceChangedWhenTheRevisionAdvances(): void
    {
        $appSource = AppSource::register('project-3', 'https://github.com/team/repo3', 'github');
        $appSource->recordRevision('rev-1');
        $this->appSources->save($appSource);
        $this->latestCommitChecker->willReturn('https://github.com/team/repo3', 'rev-2');

        $this->commandTester->execute([]);

        $published = $this->envelopesOn('source_changed');
        self::assertCount(1, $published);
        self::assertSame('rev-2', $published[0]->getMessage()->revision);
    }

    /** @return list<\Symfony\Component\Messenger\Envelope> */
    private function envelopesOn(string $transportName): array
    {
        $transport = self::getContainer()->get('messenger.transport.'.$transportName);
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport->getSent();
    }
}
