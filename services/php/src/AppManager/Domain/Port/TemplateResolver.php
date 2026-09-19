<?php

declare(strict_types=1);

namespace App\AppManager\Domain\Port;

/**
 * Resuelve qué Template del catálogo de Build corresponde a un lang/framework
 * declarado en ServiceDiscovered. El catálogo real vive en Build (BC en Go,
 * no implementado todavía) — este puerto es el punto de extensión para cuando
 * exista una forma real de consultarlo (ver appsource/discovery.md para el
 * mismo patrón: puerto listo, sin adaptador real todavía).
 */
interface TemplateResolver
{
    public function resolve(string $lang, string $framework): string;
}
