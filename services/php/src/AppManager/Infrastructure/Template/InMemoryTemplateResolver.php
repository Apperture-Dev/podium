<?php

declare(strict_types=1);

namespace App\AppManager\Infrastructure\Template;

use App\AppManager\Domain\Port\TemplateResolver;

/**
 * Test double — usado solo en el entorno de test (ver config/services.yaml,
 * when@test), hasta que exista un adaptador real contra el catálogo de Build.
 */
final class InMemoryTemplateResolver implements TemplateResolver
{
    private string $templateId = 'template-1';

    public function willReturn(string $templateId): void
    {
        $this->templateId = $templateId;
    }

    public function resolve(string $lang, string $framework): string
    {
        return $this->templateId;
    }
}
