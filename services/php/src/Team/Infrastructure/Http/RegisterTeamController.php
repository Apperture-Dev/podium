<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Http;

use App\Team\Application\ApplicationService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterTeamController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/api/teams', name: 'team_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/teams',
        summary: 'Registra un nuevo equipo — quien lo crea pasa a ser su primer miembro',
        tags: ['Team'],
    )]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RegisterTeamRequest::class)))]
    #[OA\Response(
        response: 201,
        description: 'Equipo creado',
        content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid')]),
    )]
    public function __invoke(#[MapRequestPayload] RegisterTeamRequest $request): JsonResponse
    {
        $teamId = $this->applicationService->registerTeam($request->name, $request->creatorUserId);

        return new JsonResponse(['id' => $teamId], JsonResponse::HTTP_CREATED);
    }
}
