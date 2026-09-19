<?php

declare(strict_types=1);

namespace Tests\Integration\Build;

use App\Build\Domain\Port\TemplateRepository;
use App\Build\Domain\Template;
use App\Build\Domain\ValueObject\ParamField;
use App\Build\Domain\ValueObject\TemplateId;
use RuntimeException;
use Tests\Integration\IntegrationTestCase;

final class DoctrineTemplateRepositoryTest extends IntegrationTestCase
{
    private TemplateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getService(TemplateRepository::class);
    }

    public function testItCanPersistAndRetrieveById(): void
    {
        $template = Template::define('node', 'express', 'ghcr.io/podium/buildah-node:latest', [
            'startCommand' => new ParamField('string', false, '^[a-z0-9 ]+$'),
        ]);

        $this->repository->save($template);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($template->id());

        self::assertTrue($retrieved->id()->equals($template->id()));
        self::assertSame('node', $retrieved->language());
        self::assertSame('ghcr.io/podium/buildah-node:latest', $retrieved->jobImage());
        self::assertEquals(new ParamField('string', false, '^[a-z0-9 ]+$'), $retrieved->paramSchema()['startCommand']);
    }

    public function testGetThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->repository->get(TemplateId::generate());
    }
}
