<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture;

/**
 * /api/v002/criteres-internes/
 *
 * @see https://dev.apidae-tourisme.com/fr/documentation-technique/v2/api-decriture/v002criteres-internes
 */
final class CriteresInternesService
{
    public function __construct(private readonly ApidaeClient $client)
    {
    }

    /**
     * Ajoute (PUT) ou retire (DELETE) des critères internes sur des offres.
     *
     * Ne lève pas d'exception sur une erreur de l'API (comportement historique) : renvoie ok=false,
     * le détail de l'erreur étant dans la réponse (ApidaeEcriture::lastResult()).
     * Si des offres ont été ignorées, warning les liste.
     *
     * @param 'PUT'|'DELETE' $method
     * @param array<int> $id_offres
     * @param array<int> $id_criteres
     * @return array{ok: bool, warning: ?string}
     */
    public function critereInterne(string $method, array $id_offres, array $id_criteres): array
    {
        if (!in_array($method, ['PUT', 'DELETE'], true)) {
            throw new \Exception('bad method');
        }

        $offres = array_values(array_unique($id_offres, SORT_REGULAR));
        $criteres = array_values(array_unique($id_criteres, SORT_REGULAR));
        if (count(array_filter($offres, 'is_int')) != count($offres)) {
            throw new \Exception('id_offres are not integers');
        }
        if (count(array_filter($criteres, 'is_int')) != count($criteres)) {
            throw new \Exception('id_criteres are not integers');
        }
        if (count($offres) == 0) {
            throw new \Exception('no id_offres');
        }
        if (count($criteres) == 0) {
            throw new \Exception('no id_criteres');
        }

        $payload = [
            'id' => $offres,
            $method == 'PUT' ? 'criteresInternesAAjouter' : 'criteresInternesASupprimer' => $criteres,
        ];

        $result = $this->client->request('/api/v002/criteres-internes/', [
            'POSTFIELDS' => ['criteres' => Response::json($payload)],
            'CUSTOMREQUEST' => $method,
            'format' => 'json',
        ]);

        $expectedStatus = $method == 'PUT' ? 'AJOUT_CRITERES' : 'SUPPRESSION_CRITERES';
        if (!isset($result['id'], $result['status']) || $result['status'] !== $expectedStatus || !is_array($result['id'])) {
            return ['ok' => false, 'warning' => null];
        }

        /**
         * L'enregistement s'est bien déroulé, mais il est possible que des offres aient été ignorées.
         */
        $warning = null;
        if (count($result['id']) != count($offres)) {
            $warning = count($result['id']) . ' offers updated, ' . count($offres) . ' sent (' . implode(', ', array_diff($offres, $result['id'])) . ' not updated).';
        }

        return ['ok' => true, 'warning' => $warning];
    }
}
