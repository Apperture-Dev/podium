<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Http;

use App\Project\Application\ApplicationService;
use App\Shared\Domain\Exception\AccessDeniedException;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class GetProjectController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/api/teams/{teamId}/projects/{projectId}', name: 'project_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/teams/{teamId}/projects/{projectId}',
        summary: 'Obtiene un Project — el usuario autenticado debe ser miembro del Team dueño',
        tags: ['Project'],
        parameters: [
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Project',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'hash', type: 'string'),
            new OA\Property(property: 'repositoryUrl', type: 'string'),
            new OA\Property(property: 'teamId', type: 'string', format: 'uuid'),
        ]),
    )]
    #[OA\Response(response: 401, description: 'Bearer token ausente o inválido')]
    #[OA\Response(response: 403, description: 'El usuario no es miembro del Team dueño')]
    #[OA\Response(response: 404, description: 'El projectId no existe, o no pertenece a ese teamId')]
    public function __invoke(string $teamId, string $projectId, #[CurrentUser] UserInterface $user): JsonResponse
    {
        try {
            $project = $this->applicationService->getProject($teamId, $projectId, $user->getUserIdentifier());
        } catch (AccessDeniedException) {
            return new JsonResponse(['error' => \sprintf('User cannot access team "%s".', $teamId)], JsonResponse::HTTP_FORBIDDEN);
        } catch (RuntimeException) {
            return new JsonResponse(['error' => \sprintf('Project "%s" not found for team "%s".', $projectId, $teamId)], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $project->id,
            'name' => $project->name,
            'hash' => $project->hash,
            'repositoryUrl' => $project->repositoryUrl,
            'teamId' => $project->teamId,
        ]);
    }
}
