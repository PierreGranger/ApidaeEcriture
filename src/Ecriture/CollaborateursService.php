<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture;

use PierreGranger\ApidaeException;

/**
 * /api/v002/collaborateurs/
 */
final class CollaborateursService
{
    public function __construct(private readonly ApidaeClient $client)
    {
    }

    /**
     * @param 'PUT'|'DELETE' $method
     * @param array<mixed> $membresCollaborateurs
     * @throws ApidaeException si l'API renvoie une erreur (le code de l'exception est le code HTTP)
     */
    public function collaborateurs(string $method, int $id, array $membresCollaborateurs, ?string $tokenSSO = null): void
    {
        if (!in_array($method, ['PUT', 'DELETE'], true)) {
            throw new \Exception('bad method');
        }

        $postfields = ['collaborateurs' => Response::json([
            'id' => $id,
            'membresCollaborateurs' => $membresCollaborateurs,
        ])];

        // Auparavant tokenSSO était placé hors de POSTFIELDS et n'était donc jamais envoyé.
        if ($tokenSSO !== null) {
            $postfields['tokenSSO'] = $tokenSSO;
        }

        $result = $this->client->request('/api/v002/collaborateurs/', [
            'POSTFIELDS' => $postfields,
            'CUSTOMREQUEST' => $method,
            'format' => 'json',
        ]);

        Response::assertSuccess($result);
    }
}
