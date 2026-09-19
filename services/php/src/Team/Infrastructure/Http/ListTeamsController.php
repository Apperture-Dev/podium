<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Http;

use App\Team\Application\ApplicationService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ListTeamsController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/api/teams', name: 'team_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/teams',
        summary: 'Lista los equipos de los que el usuario autenticado (claim `sub` del JWT) es miembro',
        tags: ['Team'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Equipos del usuario',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
        ])),
    )]
    #[OA\Response(response: 401, description: 'Bearer token ausente o inválido')]
    public function __invoke(#[CurrentUser] UserInterface $user): JsonResponse
    {
        $teams = $this->applicationService->listTeamsForUser($user->getUserIdentifier());

        return new JsonResponse(array_map(
            static fn ($dto): array => ['id' => $dto->id, 'name' => $dto->name],
            $teams,
        ));
    }
}
