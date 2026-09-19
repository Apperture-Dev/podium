<?php

declare(strict_types=1);

namespace App\Team\Domain;

use App\Team\Domain\ValueObject\TeamId;
use App\Team\Domain\ValueObject\TeamName;
use App\Team\Domain\ValueObject\UserId;

final class Team
{
    /** @param list<UserId> $members */
    private function __construct(
        private readonly TeamId $id,
        private readonly TeamName $name,
        private array $members,
    ) {
    }

    /**
     * Quien registra el Team pasa a ser automáticamente su primer miembro —
     * garantiza el invariante "mínimo 1 miembro siempre" desde la creación.
     */
    public static function register(TeamName $name, UserId $creator): self
    {
        return new self(TeamId::generate(), $name, [$creator]);
    }

    /** @param list<UserId> $members */
    public static function rehydrate(TeamId $id, TeamName $name, array $members): self
    {
        return new self($id, $name, $members);
    }

    public function id(): TeamId
    {
        return $this->id;
    }

    public function name(): TeamName
    {
        return $this->name;
    }

    /** @return list<UserId> */
    public function members(): array
    {
        return $this->members;
    }
}
