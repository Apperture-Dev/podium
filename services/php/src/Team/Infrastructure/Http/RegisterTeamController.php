<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Http;

use App\Team\Application\ApplicationService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class RegisterTeamController
{
    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/api/teams', name: 'team_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/teams',
        summary: 'Registra un nuevo equipo — el usuario autenticado (claim `sub` del JWT) pasa a ser su primer miembro',
        tags: ['Team'],
    )]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RegisterTeamRequest::class)))]
    #[OA\Response(
        response: 201,
        description: 'Equipo creado',
        content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid')]),
    )]
    #[OA\Response(response: 401, description: 'Bearer token ausente o inválido')]
    #[OA\Response(response: 422, description: 'Nombre vacío o de más de 150 caracteres')]
    public function __invoke(
        #[MapRequestPayload] RegisterTeamRequest $request,
        #[CurrentUser] UserInterface $user,
    ): JsonResponse {
        $teamId = $this->applicationService->registerTeam($request->name, $user->getUserIdentifier());

        return new JsonResponse(['id' => $teamId], JsonResponse::HTTP_CREATED);
    }
}
