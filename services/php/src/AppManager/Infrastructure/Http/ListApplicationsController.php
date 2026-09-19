<?php

declare(strict_types=1);

namespace App\AppManager\Infrastructure\Http;

use App\AppManager\Application\ApplicationService;
use App\Shared\Domain\Exception\AccessDeniedException;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ListApplicationsController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/api/teams/{teamId}/projects/{projectId}/applications', name: 'application_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/teams/{teamId}/projects/{projectId}/applications',
        summary: 'Lista las Application de un Project — el usuario autenticado debe ser miembro del Team dueño',
        tags: ['App Manager'],
        parameters: [
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Applications del Project',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'serviceName', type: 'string'),
            new OA\Property(property: 'projectId', type: 'string', format: 'uuid'),
            new OA\Property(property: 'teamId', type: 'string', format: 'uuid'),
            new OA\Property(property: 'framework', type: 'string'),
            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
            new OA\Property(property: 'state', type: 'string'),
            new OA\Property(property: 'version', type: 'string'),
            new OA\Property(property: 'hasPendingSourceChange', type: 'boolean'),
        ])),
    )]
    #[OA\Response(response: 401, description: 'Bearer token ausente o inválido')]
    #[OA\Response(response: 403, description: 'El usuario no es miembro del Team dueño')]
    #[OA\Response(response: 404, description: 'El projectId no existe, o no pertenece a ese teamId')]
    public function __invoke(string $teamId, string $projectId, #[CurrentUser] UserInterface $user): JsonResponse
    {
        try {
            $applications = $this->applicationService->listApplicationsForProject($teamId, $projectId, $user->getUserIdentifier());
        } catch (AccessDeniedException) {
            return new JsonResponse(['error' => \sprintf('User cannot access project "%s".', $projectId)], JsonResponse::HTTP_FORBIDDEN);
        } catch (RuntimeException) {
            return new JsonResponse(['error' => \sprintf('Project "%s" not found for team "%s".', $projectId, $teamId)], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(array_map(static fn ($dto): array => $dto->toArray(), $applications));
    }
}
