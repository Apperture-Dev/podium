<?php

declare(strict_types=1);

namespace App\Deploy\Domain\ValueObject;

/**
 * La necesidad de base de datos de un servicio, resuelta desde el bloque
 * `database:` de su `podium.yaml` (ver docs/podium-config/podium-yaml-guide.md).
 *
 * El equipo no declara valores, declara **dónde quiere recibirlos**:
 * `URL: ${DB_URL}` significa "ponme la cadena de conexión en DB_URL". Las
 * credenciales las genera CNPG y el chart las referencia por `secretKeyRef`,
 * así que Podium nunca las ve.
 *
 * Las claves de `vars` son las del Secret de CNPG (`dbname`, `username`…) y no
 * las del yaml: la traducción se hace aquí, una sola vez, para que el chart
 * sea un `range` sin tabla de equivalencias.
 */
final readonly class DatabaseDeclaration
{
    public const MODE_NONE = 'none';
    public const MODE_URL = 'url';
    public const MODE_PARTS = 'parts';

    /** Campo del podium.yaml → clave del Secret que genera CNPG. */
    private const LOOSE_FIELDS = [
        'database' => 'dbname',
        'user' => 'username',
        'password' => 'password',
        'host' => 'host',
        'port' => 'port',
    ];

    /** @param array<string, string> $vars */
    private function __construct(
        private string $mode,
        private string $urlVar,
        private array $vars,
    ) {
    }

    public static function none(): self
    {
        return new self(self::MODE_NONE, '', []);
    }

    /** @param array<string, mixed> $block El bloque `database:` del servicio, tal cual viene del yaml */
    public static function fromManifest(array $block): self
    {
        if (true !== ($block['enable'] ?? false)) {
            return self::none();
        }

        $urlVar = self::envVarName($block['URL'] ?? '');
        if ('' !== $urlVar) {
            // Regla del podium.yaml: si se rellena URL, esa gana — da igual
            // lo que haya en los campos sueltos.
            return new self(self::MODE_URL, $urlVar, []);
        }

        $vars = [];
        foreach (self::LOOSE_FIELDS as $field => $secretKey) {
            $envVar = self::envVarName($block[$field] ?? '');
            if ('' !== $envVar) {
                $vars[$secretKey] = $envVar;
            }
        }

        // `enable: true` sin decir dónde inyectar nada no pide ninguna base:
        // no habría forma de que la aplicación la usara.
        return [] === $vars ? self::none() : new self(self::MODE_PARTS, '', $vars);
    }

    /** @return array{mode: string, urlVar: string, vars: array<string, string>} */
    public function toArray(): array
    {
        return ['mode' => $this->mode, 'urlVar' => $this->urlVar, 'vars' => $this->vars];
    }

    /** @param array{mode?: string, urlVar?: string, vars?: array<string, string>} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['mode'] ?? self::MODE_NONE, $data['urlVar'] ?? '', $data['vars'] ?? []);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function urlVar(): string
    {
        return $this->urlVar;
    }

    /** @return array<string, string> clave del Secret de CNPG → nombre de env var */
    public function vars(): array
    {
        return $this->vars;
    }

    /** `${DB_URL}` y `DB_URL` nombran la misma variable; cualquier otra cosa no nombra ninguna. */
    private static function envVarName(mixed $declared): string
    {
        if (!\is_string($declared)) {
            return '';
        }

        $trimmed = trim($declared);
        if (preg_match('/^\$\{(.+)\}$/', $trimmed, $matches)) {
            return trim($matches[1]);
        }

        return $trimmed;
    }
}
