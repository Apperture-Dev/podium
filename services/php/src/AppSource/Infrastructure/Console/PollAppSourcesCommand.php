<?php

declare(strict_types=1);

namespace App\AppSource\Infrastructure\Console;

use App\AppSource\Application\ApplicationService;
use App\AppSource\Domain\Port\AppSourceRepository;
use App\AppSource\Infrastructure\GitHub\LatestCommitChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Detector de cambios: por cada AppSource trackeada, compara su última
 * revision conocida contra la real en GitHub — si difiere, delega en
 * AppSource\ApplicationService::recordRevision(), que ya publica
 * SourceChanged. Orquestación mínima, sin lógica propia (el "primer
 * commit" no necesita caso especial — ver AppSource::recordRevision()).
 */
#[AsCommand(name: 'app:appsource:poll', description: 'Compara la última revision conocida de cada AppSource contra GitHub y dispara SourceChanged si difiere')]
final class PollAppSourcesCommand extends Command
{
    public function __construct(
        private readonly AppSourceRepository $appSources,
        private readonly LatestCommitChecker $latestCommitChecker,
        private readonly ApplicationService $applicationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->appSources->findAll() as $appSource) {
            $latestRevision = $this->latestCommitChecker->latestRevision($appSource->repositoryUrl(), $appSource->provider());

            if ($latestRevision === $appSource->revision()) {
                continue;
            }

            $this->applicationService->recordRevision($appSource->id()->toString(), $latestRevision);

            $output->writeln(\sprintf(
                'AppSource %s (%s): %s -> %s',
                $appSource->id()->toString(),
                $appSource->repositoryUrl(),
                $appSource->revision() ?? '(sin revision previa)',
                $latestRevision,
            ));
        }

        return Command::SUCCESS;
    }
}
