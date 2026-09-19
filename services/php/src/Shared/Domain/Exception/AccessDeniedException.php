<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use RuntimeException;

/**
 * El userId autenticado existe, pero no tiene acceso al recurso pedido
 * (no es miembro del Team dueño). Distinto de "no encontrado" — los
 * controladores la traducen a 403, no a 404.
 */
final class AccessDeniedException extends RuntimeException
{
}
