<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Http;

use App\Project\Application\ApplicationService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

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
        summary: 'Registra un nuevo Project a partir de una repositoryUrl',
        tags: ['Project'],
    )]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RegisterProjectRequest::class)))]
    #[OA\Response(
        response: 201,
        description: 'Project creado',
        content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid')]),
    )]
    #[OA\Response(response: 404, description: 'El teamId no corresponde a ningún Team registrado')]
    public function __invoke(#[MapRequestPayload] RegisterProjectRequest $request): JsonResponse
    {
        try {
            $projectId = $this->applicationService->registerProject($request->repositoryUrl, $request->teamId);
        } catch (RuntimeException) {
            return new JsonResponse(['error' => \sprintf('Team "%s" not found.', $request->teamId)], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['id' => $projectId], JsonResponse::HTTP_CREATED);
    }
}
