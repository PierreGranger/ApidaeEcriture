<?php

namespace PierreGranger;

trait DonneesPrivees
{
    /**
     * @see https://dev.apidae-tourisme.com/documentation-technique/v2/api-decriture/v002donnees-privees/
     *
     * @param string $method PUT|DELETE
     * @param int $idFiche
     * @param string $cle
     * @param array<string> $valeurs ['fr' => 'Valeur FR', 'en' => 'Valeur EN']
     * @param string $tokenSSO
     * @return void
     */
    public function descriptifsPrives(string $method, int $idFiche, string $cle, array|null $valeurs, string $tokenSSO = null)
    {
        if (!in_array($method, ['PUT', 'DELETE'])) {
            throw new \Exception('bad method');
        }

        $donneesPrivees = ['objetsTouristiques' => []];

        /* Pour chaque objet touristique à modifer on peut avoir 1 ou plusieurs descriptifs privés à modifier. On va les stocker dans $descriptifsPrives. */
        $descriptifsPrives = [];

        $descriptif = [] ;
        if ($method == 'PUT') {
            foreach ($valeurs as $lng => $valeur) {
                $descriptif['libelle'.ucfirst($lng)] = $valeur ;
            }

            $descriptifsPrives = [
                [
                    'nomTechnique' => $cle,
                    'descriptif' => $descriptif
                ]
            ];

            /* Pour chaque objet à modifier on ajoute une entrée dans $donneesPrivees['objetsTouristiques'] */
            $donneesPrivees['objetsTouristiques'][] = [
                'id' => $idFiche,
                'donneesPrivees' => $descriptifsPrives
            ];
        } elseif ($method == 'DELETE') {
            $donneesPrivees['objetsTouristiques'][] = [
                'id' => $idFiche,
                'donneesPriveesASupprimer' => [$cle]
            ];
        }

        /* On a construit notre tableau en php : on l'encode en json pour l'envoyer à l'API. */
        $POSTFIELDS = ['donneesPrivees' => json_encode($donneesPrivees)];


        $access_token = $this->gimme_token();

        $requestData = [
            'token' => $access_token,
            'POSTFIELDS' => $POSTFIELDS,
            'CUSTOMREQUEST' => $method,
            'format' => 'json'
        ];

        if ($tokenSSO !== null) {
            $requestData['tokenSSO'] = $tokenSSO;
        }

        $result = $this->request('/api/v002/donnees-privees/', $requestData);
        $lastResult = $this->lastResult() ;

        if (!isset($result['code'])) {
            throw new \Exception('Unknown error', $lastResult->code);
        }

        if ($result['code'] != 200) {
            if (isset($result['errorType'])) {
                throw new \Exception($result['errorType'] . ' ' . @$result['message'], $result['code']);
            }
            if (isset($result['message'])) {
                throw new \Exception($result['message'], $result['code']);
            }
            throw new \Exception('Unknown error', $result['code']);
        }

        if (! isset($result['status']) || ! in_array($result['status'], ['MODIFICATION_DONNEES_PRIVEES','SUPPRESSION_DONNEES_PRIVEES'])) {
            throw new \Exception('enregistrerDonneesPrivees : Unknown status (' . @$result['status'] . ')');
        }

        return true;
    }

    /**
     * @deprecated 0.6.1
     */
    public function enregistrerDonneesPrivees(int $idFiche, string $cle, array $valeurs, string $tokenSSO = null)
    {
        return $this->descriptifsPrives('PUT', $idFiche, $cle, $valeurs, $tokenSSO) ;
    }
    public function putDescriptifsPrives(int $idFiche, string $cle, array $valeurs, string $tokenSSO = null)
    {
        return $this->descriptifsPrives('PUT', $idFiche, $cle, $valeurs, $tokenSSO) ;
    }

    /**
     * @deprecated 0.6.1
     */
    public function supprimerDonneesPrivees(int $idFiche, string $cle, string $tokenSSO = null)
    {
        return $this->descriptifsPrives('DELETE', $idFiche, $cle, null, $tokenSSO) ;
    }
    public function deleteDescriptifsPrives(int $idFiche, string $cle, string $tokenSSO = null)
    {
        return $this->descriptifsPrives('DELETE', $idFiche, $cle, null, $tokenSSO) ;
    }

    /**
     * @see https://dev.apidae-tourisme.com/fr/documentation-technique/v2/api-decriture/v002criteres-internes
     *
     * Renvoie seulement true ou false :
     * les erreurs sont à traiter en récupérant les exception ou en analysant le contenu de ApidaeEcriture::lastResult() si le retour est false.
     */
    public function critereInterne(string $method, array $id_offres, array $id_criteres): bool
    {
        if (!in_array($method, ['PUT', 'DELETE'])) {
            throw new \Exception('bad method');
        }

        $id_offres = array_unique($id_offres);
        $id_criteres = array_unique($id_criteres);
        $offres = array_filter($id_offres, 'is_int');
        $criteres = array_filter($id_criteres, 'is_int');
        if (sizeof($offres) != sizeof($id_offres)) {
            throw new \Exception('id_offres are not integers');
        }
        if (sizeof($criteres) != sizeof($id_criteres)) {
            throw new \Exception('id_criteres are not integers');
        }
        if (sizeof($offres) == 0) {
            throw new \Exception('no id_offres');
        }
        if (sizeof($criteres) == 0) {
            throw new \Exception('no id_criteres');
        }

        $access_token = $this->gimme_token($this->projet_ecriture_clientId, $this->projet_ecriture_secret);

        $criteres = [
            'id' => $offres,
            $method == 'PUT' ? 'criteresInternesAAjouter' : 'criteresInternesASupprimer' => $criteres
        ];

        $result = $this->request('/api/v002/criteres-internes/', [
            'token' => $access_token,
            'POSTFIELDS' => ['criteres' => json_encode($criteres)],
            'CUSTOMREQUEST' => $method,
            'format' => 'json'
        ]);

        if (
            isset($result['id'])
            && isset($result['status'])
            && (
                ($method == 'PUT' && $result['status'] == 'AJOUT_CRITERES')
                || ($method == 'DELETE' && $result['status'] == 'SUPPRESSION_CRITERES')
            )
        ) {
            /**
             * L'enregistement s'est bien déroulé, mais il est possible que des offres aient été ignorées, dans ce cas on enrichit le lastResult d'un warning.
             */
            if (sizeof($result['id']) != sizeof($offres)) {
                $this->lastResult['warning'] = sizeof($result['id']) . ' offers updated, ' . sizeof($offres) . ' sent (' . implode(', ', array_diff($offres, $result['id'])) . ' not updated).';
            }
            return true;
        }

        /**
         * Une erreur s'est produite : on renvoie un simple false, le contenu de l'erreur est détaillé dans $lastResult.
         */
        return false;
    }
}
