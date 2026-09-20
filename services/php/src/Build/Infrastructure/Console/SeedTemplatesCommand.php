<?php

declare(strict_types=1);

namespace App\Build\Infrastructure\Console;

use App\Build\Application\ApplicationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Siembra el catálogo de Template — sin CRUD/HTTP propio todavía (nadie
 * más que este comando lo necesita hoy). Cada entrada usa
 * ApplicationService::defineTemplate(), el mismo camino que usaría un
 * futuro endpoint de administración.
 */
#[AsCommand(name: 'app:build:seed-templates', description: 'Siembra el catálogo de Template (lenguaje/framework → jobImage)')]
final class SeedTemplatesCommand extends Command
{
    public function __construct(private readonly ApplicationService $applicationService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // jobImage: services/build-runner (buildah genérico) — selecciona
        // internamente templates/{lang}-{framework}.Dockerfile según lo
        // que declare podium.yaml. Convención: "npm ci && npm run build
        // && npm start" (ver docs/examples/podium-example.yaml).
        //
        // Registro de GitLab, no GHCR: es donde .gitlab-ci.yml publica de
        // verdad la imagen (build-build-runner) — el valor con ghcr.io que
        // hubo aquí antes nunca se publicó a ningún sitio (404 real contra
        // el clúster, confirmado con el primer build de un repo real).
        $nestjsId = $this->applicationService->defineTemplate(
            'nodejs',
            'nestjs',
            'registry.gitlab.com/apperturedev/podium/build-runner:latest',
            3000,
            [],
        );

        $output->writeln(\sprintf('Template nodejs/nestjs creado: %s', $nestjsId));

        $nextjsId = $this->applicationService->defineTemplate(
            'nodejs',
            'nextjs',
            'registry.gitlab.com/apperturedev/podium/build-runner:latest',
            3000,
            [],
        );

        $output->writeln(\sprintf('Template nodejs/nextjs creado: %s', $nextjsId));

        return Command::SUCCESS;
    }
}
