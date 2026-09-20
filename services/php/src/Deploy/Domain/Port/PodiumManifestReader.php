<?php

declare(strict_types=1);

namespace App\Deploy\Domain\Port;

/**
 * Deploy lee del `podium.yaml` lo que le concierne — hoy, la declaración de
 * base de datos del servicio que va a desplegar. No se la pasa Build: Build
 * construye una imagen y no tiene por qué saber qué base de datos necesita
 * esa imagen para correr.
 */
interface PodiumManifestReader
{
    /** @return array<string, mixed> el bloque `database:` del servicio, vacío si no lo declara */
    public function databaseBlockFor(string $repositoryUrl, string $revision, string $serviceName): array;
}
