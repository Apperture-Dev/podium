<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Http;

use App\Project\Application\ApplicationService;
use App\Project\Domain\ValueObject\ProjectDTO;
use App\Shared\Domain\Exception\AccessDeniedException;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ListProjectsController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/api/teams/{teamId}/projects', name: 'project_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/teams/{teamId}/projects',
        summary: 'Lista los Project de un Team — el usuario autenticado debe ser miembro de ese Team',
        tags: ['Project'],
        parameters: [new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
    )]
    #[OA\Response(
        response: 200,
        description: 'Projects del Team',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'hash', type: 'string'),
            new OA\Property(property: 'repositoryUrl', type: 'string'),
            new OA\Property(property: 'teamId', type: 'string', format: 'uuid'),
            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        ])),
    )]
    #[OA\Response(response: 401, description: 'Bearer token ausente o inválido')]
    #[OA\Response(response: 403, description: 'El usuario no es miembro de ese Team')]
    #[OA\Response(response: 404, description: 'El teamId no corresponde a ningún Team registrado')]
    public function __invoke(string $teamId, #[CurrentUser] UserInterface $user): JsonResponse
    {
        try {
            $projects = $this->applicationService->listProjectsForTeam($teamId, $user->getUserIdentifier());
        } catch (AccessDeniedException) {
            return new JsonResponse(['error' => \sprintf('User cannot access team "%s".', $teamId)], JsonResponse::HTTP_FORBIDDEN);
        } catch (RuntimeException) {
            return new JsonResponse(['error' => \sprintf('Team "%s" not found.', $teamId)], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(array_map(
            static fn (ProjectDTO $dto): array => [
                'id' => $dto->id,
                'name' => $dto->name,
                'hash' => $dto->hash,
                'repositoryUrl' => $dto->repositoryUrl,
                'teamId' => $dto->teamId,
                'createdAt' => $dto->createdAt->format(\DateTimeInterface::ATOM),
            ],
            $projects,
        ));
    }
}
