<?php

declare(strict_types=1);

namespace App\AppManager\Infrastructure\Template;

use App\AppManager\Domain\Port\TemplateResolver;
use App\Build\Domain\Port\TemplateRepository;
use RuntimeException;

/**
 * Consulta el catálogo real de Template (BC Build) por lenguaje/framework —
 * lectura síncrona entre BCs, mismo patrón ya usado para leer Project.teamId
 * desde AppManager/Build/Deploy. Nunca escribe, así que no hace falta pasar
 * por eventos.
 */
final class DoctrineTemplateResolver implements TemplateResolver
{
    public function __construct(private readonly TemplateRepository $templates)
    {
    }

    public function resolve(string $lang, string $framework): string
    {
        $template = $this->templates->findByLanguageAndFramework($lang, $framework)
            ?? throw new RuntimeException(\sprintf('No Template registered for lang="%s" framework="%s".', $lang, $framework));

        return $template->id()->toString();
    }
}
