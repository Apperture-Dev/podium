<?php

declare(strict_types=1);

namespace Tests\Integration\AppManager;

use App\AppManager\Infrastructure\Template\DoctrineTemplateResolver;
use App\Build\Domain\Port\TemplateRepository;
use App\Build\Domain\Template;
use RuntimeException;
use Tests\Integration\IntegrationTestCase;

final class DoctrineTemplateResolverTest extends IntegrationTestCase
{
    private DoctrineTemplateResolver $resolver;
    private TemplateRepository $templates;

    protected function setUp(): void
    {
        parent::setUp();

        // Clase concreta, no la interfaz — when@test la aliasa a
        // InMemoryTemplateResolver (ver services.yaml), y este test quiere
        // el adaptador real.
        $this->resolver = $this->getService(DoctrineTemplateResolver::class);
        $this->templates = $this->getService(TemplateRepository::class);
    }

    public function testResolvesTheTemplateIdForARegisteredLanguageAndFramework(): void
    {
        $template = Template::define('nodejs', 'nestjs', 'ghcr.io/podium/build-runner:latest', []);
        $this->templates->save($template);
        $this->clearEntityManager();

        $templateId = $this->resolver->resolve('nodejs', 'nestjs');

        self::assertSame($template->id()->toString(), $templateId);
    }

    public function testThrowsWhenNoTemplateMatches(): void
    {
        $this->expectException(RuntimeException::class);

        $this->resolver->resolve('cobol', 'jcl');
    }
}
