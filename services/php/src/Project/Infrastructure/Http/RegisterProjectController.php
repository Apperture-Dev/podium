<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Http;

use App\Project\Application\ApplicationService;
use App\Shared\Domain\Exception\AccessDeniedException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Según project/model.md, registrar un Project crea también su AppSource "a la
 * vez" — AppSource (BC) todavía no está implementado, así que este endpoint
 * hoy solo crea el Project. Pendiente cuando se construya AppSource.
 */
final class RegisterProjectController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/api/projects', name: 'project_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/projects',
        summary: 'Registra un nuevo Project a partir de una repositoryUrl — el usuario autenticado debe ser miembro del Team indicado',
        tags: ['Project'],
    )]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RegisterProjectRequest::class)))]
    #[OA\Response(
        response: 201,
        description: 'Project creado',
        content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid')]),
    )]
    #[OA\Response(response: 401, description: 'Bearer token ausente o inválido')]
    #[OA\Response(response: 403, description: 'El usuario autenticado no es miembro del Team indicado')]
    #[OA\Response(response: 404, description: 'El teamId no corresponde a ningún Team registrado')]
    public function __invoke(
        #[MapRequestPayload] RegisterProjectRequest $request,
        #[CurrentUser] UserInterface $user,
    ): JsonResponse {
        try {
            $projectId = $this->applicationService->registerProject(
                $request->repositoryUrl,
                $request->teamId,
                $request->name,
                $user->getUserIdentifier(),
            );
        } catch (AccessDeniedException) {
            // Antes que RuntimeException a propósito: AccessDeniedException la
            // extiende, así que al revés este caso saldría como 404 "not found"
            // — un código equivocado, y sin nada que lo delate.
            return new JsonResponse(['error' => \sprintf('User cannot access team "%s".', $request->teamId)], JsonResponse::HTTP_FORBIDDEN);
        } catch (RuntimeException) {
            return new JsonResponse(['error' => \sprintf('Team "%s" not found.', $request->teamId)], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['id' => $projectId], JsonResponse::HTTP_CREATED);
    }
}
