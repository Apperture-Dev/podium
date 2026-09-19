<?php

declare(strict_types=1);

namespace App\AppManager\Infrastructure\Template;

use App\AppManager\Domain\Port\TemplateResolver;
use RuntimeException;

/**
 * Adaptador real (consultar el catálogo de Template de Build) sin implementar
 * todavía — placeholder honesto para no fingir que la resolución funciona hoy.
 */
final class NotImplementedTemplateResolver implements TemplateResolver
{
    public function resolve(string $lang, string $framework): string
    {
        throw new RuntimeException('TemplateResolver has no real adapter yet — the Build catalog does not exist.');
    }
}
