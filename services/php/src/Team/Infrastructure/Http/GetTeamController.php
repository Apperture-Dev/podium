<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Http;

use App\Shared\Domain\Exception\AccessDeniedException;
use App\Team\Application\ApplicationService;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class GetTeamController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/api/teams/{teamId}', name: 'team_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/teams/{teamId}',
        summary: 'Obtiene un Team — el usuario autenticado debe ser miembro',
        tags: ['Team'],
        parameters: [new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    )]
    #[OA\Response(
        response: 200,
        description: 'Team',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
        ]),
    )]
    #[OA\Response(response: 401, description: 'Bearer token ausente o inválido')]
    #[OA\Response(response: 403, description: 'El usuario no es miembro de ese Team')]
    #[OA\Response(response: 404, description: 'El teamId no corresponde a ningún Team registrado')]
    public function __invoke(string $teamId, #[CurrentUser] UserInterface $user): JsonResponse
    {
        try {
            $team = $this->applicationService->getTeam($teamId, $user->getUserIdentifier());
        } catch (AccessDeniedException) {
            return new JsonResponse(['error' => \sprintf('User cannot access team "%s".', $teamId)], JsonResponse::HTTP_FORBIDDEN);
        } catch (RuntimeException) {
            return new JsonResponse(['error' => \sprintf('Team "%s" not found.', $teamId)], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['id' => $team->id, 'name' => $team->name]);
    }
}
