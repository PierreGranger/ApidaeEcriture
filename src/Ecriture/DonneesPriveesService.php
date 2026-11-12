<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture;

use PierreGranger\ApidaeException;

/**
 * /api/v002/donnees-privees/
 *
 * @see https://dev.apidae-tourisme.com/documentation-technique/v2/api-decriture/v002donnees-privees/
 */
final class DonneesPriveesService
{
    public function __construct(private readonly ApidaeClient $client)
    {
    }

    /**
     * Ajoute/modifie (PUT) ou supprime (DELETE) un descriptif privé sur une offre.
     *
     * @param 'PUT'|'DELETE' $method
     * @param array<string, string>|null $valeurs ['fr' => 'Valeur FR', 'en' => 'Valeur EN'] (obligatoire en PUT)
     */
    public function descriptifsPrives(string $method, int $idFiche, string $cle, ?array $valeurs, ?string $tokenSSO = null): void
    {
        if ($method == 'PUT') {
            if (empty($valeurs)) {
                throw new \Exception('$valeurs is required for PUT');
            }
            $descriptif = [];
            foreach ($valeurs as $lng => $valeur) {
                $descriptif['libelle' . ucfirst((string) $lng)] = $valeur;
            }
            $payload = ['objetsTouristiques' => [[
                'id' => $idFiche,
                'donneesPrivees' => [['nomTechnique' => $cle, 'descriptif' => $descriptif]],
            ]]];
        } elseif ($method == 'DELETE') {
            $payload = ['objetsTouristiques' => [[
                'id' => $idFiche,
                'donneesPriveesASupprimer' => [$cle],
            ]]];
        } else {
            throw new \Exception('bad method');
        }

        $this->donneesPrivees($method, $payload, $tokenSSO);
    }

    /**
     * Traitement par lot : $payload est envoyé tel quel après validation de sa structure.
     *
     * @param 'PUT'|'DELETE' $method
     * @param array<string, mixed> $payload ['objetsTouristiques' => [...]]
     * @throws ApidaeException si l'API renvoie une erreur (le code de l'exception est le code HTTP)
     */
    public function donneesPrivees(string $method, array $payload, ?string $tokenSSO = null): void
    {
        if (!in_array($method, ['PUT', 'DELETE'], true)) {
            throw new \Exception('bad method');
        }

        self::validatePayload($method, $payload);

        $postfields = ['donneesPrivees' => Response::json($payload)];

        // Auparavant tokenSSO était placé hors de POSTFIELDS et n'était donc jamais envoyé.
        if ($tokenSSO !== null) {
            $postfields['tokenSSO'] = $tokenSSO;
        }

        $result = $this->client->request('/api/v002/donnees-privees/', [
            'POSTFIELDS' => $postfields,
            'CUSTOMREQUEST' => $method,
            'format' => 'json',
        ]);

        Response::assertSuccess($result);

        if (!isset($result['status']) || !in_array($result['status'], ['MODIFICATION_DONNEES_PRIVEES', 'SUPPRESSION_DONNEES_PRIVEES'], true)) {
            throw new ApidaeException('enregistrerDonneesPrivees : Unknown status (' . (is_scalar($result['status'] ?? null) ? $result['status'] : '') . ')', ApidaeException::UNIDENTIFIED_ERROR, ['result' => $result]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function validatePayload(string $method, array $payload): void
    {
        if (!isset($payload['objetsTouristiques']) || !is_array($payload['objetsTouristiques'])) {
            throw new \Exception('bad format for $donneesPrivees');
        }

        foreach ($payload['objetsTouristiques'] as $objetTouristique) {
            if (!isset($objetTouristique['id']) || !is_int($objetTouristique['id'])) {
                throw new \Exception('bad format for $payload.objetsTouristiques.id');
            }

            if ($method == 'PUT') {
                if (!isset($objetTouristique['donneesPrivees']) || !is_array($objetTouristique['donneesPrivees'])) {
                    throw new \Exception('bad format for $payload.objetsTouristiques.donneesPrivees');
                }
                foreach ($objetTouristique['donneesPrivees'] as $donneePrivee) {
                    if (!isset($donneePrivee['nomTechnique']) || !is_string($donneePrivee['nomTechnique'])) {
                        throw new \Exception('bad format for $payload.objetsTouristiques.donneesPrivees.nomTechnique');
                    }
                    if (!isset($donneePrivee['descriptif']) || !is_array($donneePrivee['descriptif'])) {
                        throw new \Exception('bad format for $payload.objetsTouristiques.donneesPrivees.descriptif');
                    }
                }
            } else {
                if (!isset($objetTouristique['donneesPriveesASupprimer']) || !is_array($objetTouristique['donneesPriveesASupprimer'])) {
                    throw new \Exception('bad format for $payload.objetsTouristiques.donneesPriveesASupprimer');
                }
                foreach ($objetTouristique['donneesPriveesASupprimer'] as $donneePriveeASupprimer) {
                    if (!is_string($donneePriveeASupprimer)) {
                        throw new \Exception('bad format for $payload.objetsTouristiques.donneesPriveesASupprimer');
                    }
                }
            }
        }
    }
}
