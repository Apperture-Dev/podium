<?php

declare(strict_types=1);

namespace Tests\Unit\Build\Domain;

use App\Build\Domain\Template;
use App\Build\Domain\ValueObject\ParamField;
use Tests\Unit\UnitTestCase;

final class TemplateTest extends UnitTestCase
{
    private Template $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->template = Template::define('node', 'express', 'ghcr.io/podium/buildah-node:latest', 3000, [
            'startCommand' => new ParamField('string', false, '^[a-z0-9 ]+$'),
        ]);
    }

    public function testDefineCreatesATemplateWithItsCatalogData(): void
    {
        self::assertSame('node', $this->template->language());
        self::assertSame('express', $this->template->framework());
        self::assertSame('ghcr.io/podium/buildah-node:latest', $this->template->jobImage());
        self::assertSame(3000, $this->template->defaultPort());
        self::assertArrayHasKey('startCommand', $this->template->paramSchema());
    }

    public function testParamSchemaFieldsKeepTheirShapeConstraint(): void
    {
        $field = $this->template->paramSchema()['startCommand'];

        self::assertSame('string', $field->type);
        self::assertFalse($field->required);
        self::assertSame('^[a-z0-9 ]+$', $field->shapeConstraint);
    }
}
