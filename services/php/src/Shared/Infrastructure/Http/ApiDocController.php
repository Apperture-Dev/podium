<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Página estática de Swagger UI (vía CDN), apuntando a /api/doc.json.
 * El renderer HTML de nelmio/api-doc-bundle exige symfony/twig-bundle +
 * symfony/asset — deliberadamente no instalados (API pura, sin vistas) —
 * así que servimos Swagger UI directo en vez de arrastrar Twig solo por esto.
 */
final class ApiDocController
{
    #[Route('/api/doc', name: 'api_doc_ui', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response(<<<'HTML'
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8" />
                <title>Podium API</title>
                <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
                <script>
                    window.onload = () => {
                        window.ui = SwaggerUIBundle({
                            url: '/api/doc.json',
                            dom_id: '#swagger-ui',
                        });
                    };
                </script>
            </body>
            </html>
            HTML,
            Response::HTTP_OK,
            ['Content-Type' => 'text/html'],
        );
    }
}
