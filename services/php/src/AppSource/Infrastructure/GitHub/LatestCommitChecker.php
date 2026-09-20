<?php

declare(strict_types=1);

namespace App\AppSource\Infrastructure\GitHub;

/**
 * Punto de sustitución de infraestructura para poder testear
 * PollAppSourcesCommand sin red real — NO es un puerto de dominio (ver
 * appsource/model.md: "de dónde venga la revision es infraestructura, no
 * dominio"), es el mismo patrón de test double que ya usa este repo para
 * PodiumManifestReader/TemplateResolver.
 */
interface LatestCommitChecker
{
    public function latestRevision(string $repositoryUrl, string $provider): string;
}
