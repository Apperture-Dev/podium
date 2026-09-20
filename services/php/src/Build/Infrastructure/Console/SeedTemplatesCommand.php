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
 * futuro endpoint de administración, y se puede re-ejecutar sin duplicar
 * lo ya definido.
 */
#[AsCommand(name: 'app:build:seed-templates', description: 'Siembra el catálogo de Template (lenguaje/framework → jobImage)')]
final class SeedTemplatesCommand extends Command
{
    /**
     * jobImage: services/build-runner (buildah genérico) — selecciona
     * internamente templates/{lang}-{framework}.Dockerfile según lo que
     * declare podium.yaml.
     *
     * Registro de GitLab, no GHCR: es donde .gitlab-ci.yml publica de verdad
     * la imagen (build-build-runner) — el valor con ghcr.io que hubo aquí
     * antes nunca se publicó a ningún sitio (404 real contra el clúster,
     * confirmado con el primer build de un repo real).
     */
    private const JOB_IMAGE = 'registry.gitlab.com/apperturedev/podium/build-runner:latest';

    /**
     * El puerto es el que expone el Dockerfile de plantilla correspondiente,
     * y acaba tal cual en `values.port` del chart podium-app — el chart no
     * inyecta `PORT`, así que cada imagen tiene que escuchar exactamente en
     * el suyo: nginx sirve las SPA en el 80, FrankenPHP también, uvicorn en
     * el 8000, y para Go y Rust se fija el 8080 por convención (ver el
     * README de services/build-runner).
     *
     * `go`/`stdlib` y `rust`/`cargo` nombran la cadena de build, no una
     * librería: el Dockerfile compila y ejecuta el binario igual con gin,
     * echo, axum o actix por encima.
     *
     * @var list<array{string, string, int}>
     */
    private const TEMPLATES = [
        ['nodejs', 'nestjs', 3000],
        ['nodejs', 'nextjs', 3000],
        ['nodejs', 'react', 80],
        ['nodejs', 'vue', 80],
        ['python', 'fastapi', 8000],
        ['php', 'laravel', 80],
        ['php', 'symfony', 80],
        ['php', 'symfony-worker', 80],
        ['go', 'stdlib', 8080],
        ['rust', 'cargo', 8080],
    ];

    public function __construct(private readonly ApplicationService $applicationService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (self::TEMPLATES as [$language, $framework, $defaultPort]) {
            $templateId = $this->applicationService->defineTemplate(
                $language,
                $framework,
                self::JOB_IMAGE,
                $defaultPort,
                [],
            );

            $output->writeln(\sprintf('Template %s/%s (:%d): %s', $language, $framework, $defaultPort, $templateId));
        }

        return Command::SUCCESS;
    }
}
