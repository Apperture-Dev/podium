<?php

declare(strict_types=1);

namespace Tests\Unit\Deploy\Domain;

use App\Deploy\Domain\ValueObject\DatabaseDeclaration;
use Tests\Unit\UnitTestCase;

final class DatabaseDeclarationTest extends UnitTestCase
{
    public function testItIsDisabledWhenTheServiceDeclaresNoDatabaseBlock(): void
    {
        $declaration = DatabaseDeclaration::fromManifest([]);

        self::assertSame('none', $declaration->mode());
    }

    /** `enable` es false por defecto: declarar el bloque sin activarlo no monta nada. */
    public function testItIsDisabledWhenEnableIsAbsentOrFalse(): void
    {
        self::assertSame('none', DatabaseDeclaration::fromManifest(['URL' => '${DB_URL}'])->mode());
        self::assertSame('none', DatabaseDeclaration::fromManifest(['enable' => false, 'URL' => '${DB_URL}'])->mode());
    }

    public function testItResolvesTheUrlShapeToTheRequestedEnvVar(): void
    {
        $declaration = DatabaseDeclaration::fromManifest([
            'enable' => true,
            'URL' => '${DB_URL}',
        ]);

        self::assertSame('url', $declaration->mode());
        self::assertSame('DB_URL', $declaration->urlVar());
        self::assertSame([], $declaration->vars());
    }

    /**
     * Las claves resultantes son las del Secret que genera CNPG, no las del
     * podium.yaml: así el chart las copia sin traducir nada.
     */
    public function testItResolvesTheLooseFieldsToTheSecretKeysOfCnpg(): void
    {
        $declaration = DatabaseDeclaration::fromManifest([
            'enable' => true,
            'database' => '${APP_DATABASE}',
            'user' => '${APP_DATABASE_USER}',
            'password' => '${APP_DATABASE_PASSWORD}',
            'host' => '${APP_DATABASE_HOST}',
            'port' => '${APP_DATABASE_PORT}',
        ]);

        self::assertSame('parts', $declaration->mode());
        self::assertSame('', $declaration->urlVar());
        self::assertSame([
            'dbname' => 'APP_DATABASE',
            'username' => 'APP_DATABASE_USER',
            'password' => 'APP_DATABASE_PASSWORD',
            'host' => 'APP_DATABASE_HOST',
            'port' => 'APP_DATABASE_PORT',
        ], $declaration->vars());
    }

    /** Regla del podium.yaml: "si se rellena URL, esa gana". */
    public function testUrlWinsOverTheLooseFields(): void
    {
        $declaration = DatabaseDeclaration::fromManifest([
            'enable' => true,
            'URL' => '${DB_URL}',
            'database' => '${APP_DATABASE}',
            'user' => '${APP_DATABASE_USER}',
        ]);

        self::assertSame('url', $declaration->mode());
        self::assertSame('DB_URL', $declaration->urlVar());
        self::assertSame([], $declaration->vars());
    }

    /** Un campo suelto que el equipo no declara no genera env var vacía. */
    public function testItIgnoresTheLooseFieldsLeftOut(): void
    {
        $declaration = DatabaseDeclaration::fromManifest([
            'enable' => true,
            'host' => '${APP_DATABASE_HOST}',
            'port' => '',
        ]);

        self::assertSame(['host' => 'APP_DATABASE_HOST'], $declaration->vars());
    }

    /** `enable: true` a secas no dice dónde inyectar nada: no hay base que montar. */
    public function testItIsDisabledWhenEnabledButNothingIsRequested(): void
    {
        self::assertSame('none', DatabaseDeclaration::fromManifest(['enable' => true])->mode());
    }

    public function testItAcceptsANameWrittenWithoutTheInterpolationBraces(): void
    {
        $declaration = DatabaseDeclaration::fromManifest(['enable' => true, 'URL' => 'DB_URL']);

        self::assertSame('DB_URL', $declaration->urlVar());
    }

    /** El shape que viaja en el evento es exactamente el que espera el chart. */
    public function testItTravelsAsThePlainValuesTheChartExpects(): void
    {
        $declaration = DatabaseDeclaration::fromManifest([
            'enable' => true,
            'database' => '${APP_DATABASE}',
        ]);

        self::assertSame([
            'mode' => 'parts',
            'urlVar' => '',
            'vars' => ['dbname' => 'APP_DATABASE'],
        ], $declaration->toArray());

        self::assertEquals($declaration, DatabaseDeclaration::fromArray($declaration->toArray()));
    }
}
