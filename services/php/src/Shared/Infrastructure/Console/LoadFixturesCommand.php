<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\AppManager\Application\ApplicationService as AppManagerApplicationService;
use App\Project\Application\ApplicationService as ProjectApplicationService;
use App\Team\Application\ApplicationService as TeamApplicationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Datos de mentira para rellenar el frontend en desarrollo — 2 Team, 3
 * Project, 4 Application en distintos estados. `userId` es obligatorio
 * porque tiene que ser el `sub` real del usuario con el que se va a
 * autenticar el frontend (JWT de Keycloak) para que /api/teams le
 * devuelva algo — no hay forma de adivinarlo desde el código. Requiere
 * el Template nodejs/nestjs ya sembrado (`app:build:seed-templates`).
 */
#[AsCommand(name: 'app:fixtures:load', description: 'Siembra datos de mentira (teams, projects, applications) para el frontend')]
final class LoadFixturesCommand extends Command
{
    public function __construct(
        private readonly TeamApplicationService $teams,
        private readonly ProjectApplicationService $projects,
        private readonly AppManagerApplicationService $applications,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('userId', InputArgument::REQUIRED, 'sub del JWT de Keycloak con el que se autenticará el frontend — se le añade como miembro de los teams de mentira');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ('prod' === $this->environment) {
            $output->writeln('<error>No se puede sembrar datos de mentira en prod.</error>');

            return Command::FAILURE;
        }

        $userId = $input->getArgument('userId');

        $squadTeamId = $this->teams->registerTeam('Podium Squad', $userId);
        $output->writeln(\sprintf('Team "Podium Squad": %s', $squadTeamId));

        $marketplaceId = $this->projects->registerProject('https://github.com/podium-hackathon/marketplace-api', $squadTeamId, 'Marketplace API');
        $output->writeln(\sprintf('  Project "Marketplace API": %s', $marketplaceId));
        $this->deployedApplication($marketplaceId, 'backend');
        $this->buildFailedApplication($marketplaceId, 'worker');

        $landingId = $this->projects->registerProject('https://github.com/podium-hackathon/landing-page', $squadTeamId, 'Landing Page');
        $output->writeln(\sprintf('  Project "Landing Page": %s', $landingId));
        $this->buildingApplication($landingId, 'frontend');

        $judgesTeamId = $this->teams->registerTeam('Judges Demo Team', $userId);
        $output->writeln(\sprintf('Team "Judges Demo Team": %s', $judgesTeamId));

        $analyticsId = $this->projects->registerProject('https://github.com/podium-hackathon/analytics-service', $judgesTeamId, 'Analytics Service');
        $output->writeln(\sprintf('  Project "Analytics Service": %s', $analyticsId));
        $this->deployFailedApplication($analyticsId, 'api');

        return Command::SUCCESS;
    }

    private function deployedApplication(string $projectId, string $serviceName): void
    {
        $this->applications->registerApplication($serviceName, $projectId, 'nodejs', 'nestjs');
        $this->applications->markSourceChanged($projectId, $serviceName, 'a1b2c3d', 'https://github.com/podium-hackathon/marketplace-api', 'github');
        $this->applications->markBuildSucceeded($projectId, $serviceName, 'registry.podium.dev/marketplace-api-backend:a1b2c3d', [], []);
        $this->applications->markDeploySucceeded($projectId, $serviceName);
    }

    private function buildFailedApplication(string $projectId, string $serviceName): void
    {
        $this->applications->registerApplication($serviceName, $projectId, 'nodejs', 'nestjs');
        $this->applications->markSourceChanged($projectId, $serviceName, 'b2c3d4e', 'https://github.com/podium-hackathon/marketplace-api', 'github');
        $this->applications->markBuildFailed($projectId, $serviceName);
    }

    private function buildingApplication(string $projectId, string $serviceName): void
    {
        $this->applications->registerApplication($serviceName, $projectId, 'nodejs', 'nestjs');
        $this->applications->markSourceChanged($projectId, $serviceName, 'c3d4e5f', 'https://github.com/podium-hackathon/landing-page', 'github');
    }

    private function deployFailedApplication(string $projectId, string $serviceName): void
    {
        $this->applications->registerApplication($serviceName, $projectId, 'nodejs', 'nestjs');
        $this->applications->markSourceChanged($projectId, $serviceName, 'd4e5f6a', 'https://github.com/podium-hackathon/analytics-service', 'github');
        $this->applications->markBuildSucceeded($projectId, $serviceName, 'registry.podium.dev/analytics-service-api:d4e5f6a', [], []);
        $this->applications->markDeployFailed($projectId, $serviceName);
    }
}
