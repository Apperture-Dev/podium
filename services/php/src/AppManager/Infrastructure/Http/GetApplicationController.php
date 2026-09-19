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

/**
 * `{serviceName}`, no el id técnico interno — es el identificador de
 * negocio de Application, único dentro de su Project (ver
 * app-manager/model.md), y el único que el listado ya expone.
 */
final class GetApplicationController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/api/teams/{teamId}/projects/{projectId}/applications/{serviceName}', name: 'application_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/teams/{teamId}/projects/{projectId}/applications/{serviceName}',
        summary: 'Obtiene una Application por serviceName — el usuario autenticado debe ser miembro del Team dueño',
        tags: ['App Manager'],
        parameters: [
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'serviceName', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Application',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'serviceName', type: 'string'),
            new OA\Property(property: 'projectId', type: 'string', format: 'uuid'),
            new OA\Property(property: 'teamId', type: 'string', format: 'uuid'),
            new OA\Property(property: 'framework', type: 'string'),
            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
            new OA\Property(property: 'state', type: 'string'),
            new OA\Property(property: 'version', type: 'string'),
            new OA\Property(property: 'hasPendingSourceChange', type: 'boolean'),
        ]),
    )]
    #[OA\Response(response: 401, description: 'Bearer token ausente o inválido')]
    #[OA\Response(response: 403, description: 'El usuario no es miembro del Team dueño')]
    #[OA\Response(response: 404, description: 'El project o el serviceName no existen bajo ese teamId/projectId')]
    public function __invoke(string $teamId, string $projectId, string $serviceName, #[CurrentUser] UserInterface $user): JsonResponse
    {
        try {
            $application = $this->applicationService->getApplication($teamId, $projectId, $serviceName, $user->getUserIdentifier());
        } catch (AccessDeniedException) {
            return new JsonResponse(['error' => \sprintf('User cannot access project "%s".', $projectId)], JsonResponse::HTTP_FORBIDDEN);
        } catch (RuntimeException) {
            return new JsonResponse(['error' => \sprintf('Application "%s" not found.', $serviceName)], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($application->toArray());
    }
}
