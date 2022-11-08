<?php

namespace PierreGranger;

use PierreGranger\ApidaeCore;
use PierreGranger\Collaborateurs;
use PierreGranger\DonneesPrivees;

/**
 *
 * @author  Pierre Granger <pierre@pierre-granger.fr>
 *
 */

class ApidaeEcriture extends ApidaeCore
{
    use Collaborateurs ;
    use DonneesPrivees ;

    protected bool $skipValidation = false;
    protected string $onValidationFail ;

    public const STATUS_API_ECRITURE = [
        'CREATION_VALIDATION_SKIPPED', 'CREATION_VALIDATION_ASKED',
        'MODIFICATION_VALIDATION_SKIPPED', 'MODIFICATION_VALIDATION_ASKED',
        'MODIFICATION_NO_DIFF',
        'DEMANDE_SUPPRESSION_SENT',
        'NO_ACTION'
    ];

    public const MODES = [
        'CREATION', 'MODIFICATION', 'DEMANDE_SUPPRESSION',
        'ANNULATION_DEMANDE', /** @since 0.6.0 */
        'DEMANDE_MASQUAGE', /** @since 0.6.0 */
        'MASQUAGE' /** @since 0.6.0 */
    ];

    public const ID_REQUIRED = [
        'MODIFICATION','DEMANDE_SUPPRESSION',
        'ANNULATION_DEMANDE', /** @since 0.6.0 */
        'DEMANDE_MASQUAGE', /** @since 0.6.0 */
        'MASQUAGE', /** @since 0.6.0 */
    ] ;

    public const ONVALIDATIONFAIL = ['ASK','CANCEL'] ;

    public const ERRORTYPES = [
        'ECRITURE_FORBIDDEN',
        'ECRITURE_BAD_REQUEST',
        'ECRITURE_INVALID_JSON_DATA',
        'DEMANDE_SUPPRESSION_FAILED', /** @since 0.6.0 */
        'DEMANDE_MASQUAGE_FAILED', /** @since 0.6.0 */
        'MODIFICATION_VALIDATION_FAILED', /** @since 0.6.0 */
        'CREATION_VALIDATION_FAILED', /** @since 0.6.0 */
        'CANCEL_FAILED' /** @since 0.6.0 */
    ] ;

    public const FIELDS_REQUIRED = ['CREATION','MODIFICATION'] ;

    protected $_config;

    public $last_id = null;

    public string $projet_ecriture_clientId;
    protected string $projet_ecriture_secret;
    public int $projet_ecriture_projectId;

    protected $lastAutorisation;

    public function __construct(array $params = null)
    {
        parent::__construct($params);

        if (!is_array($params)) {
            throw new \Exception('$params is not an array');
        }

        if (isset($params['projet_ecriture_clientId'])) {
            $this->projet_ecriture_clientId = $params['projet_ecriture_clientId'];
        } else {
            throw new \Exception('missing projet_ecriture_clientId');
        }
        if (isset($params['projet_ecriture_secret'])) {
            $this->projet_ecriture_secret = $params['projet_ecriture_secret'];
        } else {
            throw new \Exception('missing projet_ecriture_secret');
        }
        if (isset($params['projet_ecriture_projectId'])) {
            $this->projet_ecriture_projectId = $params['projet_ecriture_projectId'];
        }

        if (isset($params['skipValidation'])) {
            $this->skipValidation = $params['skipValidation'] ? true : false;
        }

        if (isset($params['onValidationFail']) && in_array($params['onValidationFail'], self::ONVALIDATIONFAIL)) {
            $this->onValidationFail = $params['onValidationFail'];
        }
    }

    public function enregistrer($params = null): bool
    {
        if (!is_array($params)) {
            throw new \Exception('enregistrer_params_not_array');
        }

        if (!isset($params['action']) || !in_array($params['action'], self::MODES)) {
            throw new \Exception('enregistrer_action_null');
        }
        if (!in_array($params['action'], self::MODES)) {
            throw new \Exception(__METHOD__ . ' : Action ' . $params['action'] . ' invalide');
        }
        $action = $params['action'];

        $postfields = [];

        $postfields['mode'] = $action;
        if (in_array($postfields['mode'], self::ID_REQUIRED)) {
            if (isset($params['idFiche'])) {
                $postfields['id'] = $params['idFiche'];
            } elseif (isset($params['id'])) {
                $postfields['id'] = $params['id'];
            } else {
                throw new \Exception(__METHOD__ . ' : Identifiant de fiche non trouvé (id, idFiche ?)');
            }
        }

        // skipValidation doit être un string
        if (isset($params['skipValidation'])) {
            $postfields['skipValidation'] = $params['skipValidation'] ? 'true' : 'false';
        } else {
            $postfields['skipValidation'] = $this->skipValidation ? 'true' : 'false';
        }

        if (isset($params['onValidationFail']) && in_array($params['onValidationFail'], self::ONVALIDATIONFAIL)) {
            $postfields['onValidationFail'] = $params['onValidationFail'] ;
        } elseif (isset($this->onValidationFail) && in_array($this->onValidationFail, self::ONVALIDATIONFAIL)) {
            $postfields['onValidationFail'] = $this->onValidationFail ;
        }

        if (isset($params['tokenSSO'])) {
            $postfields['tokenSSO'] = $params['tokenSSO'];
        }

        if (in_array($postfields['mode'], self::FIELDS_REQUIRED)) {
            /**
             * Le paramètre "type" est obligatoire pour la création (ne pas confondre avec root={"type":"EQUIPEMENT"})
             * Auparavant le paramètre était récupéré de root.type mais ce n'était pas techniquement très juste
             */
            if ($postfields['mode'] == 'CREATION') {
                if (isset($params['type'])) {
                    $postfields['type'] = $params['type'];
                }
                // Rétro compatibilité
                elseif (isset($params['root']['type'])) {
                    $postfields['type'] = $params['root']['type'];
                } else {
                    throw new \Exception('Le paramètre "type" est obligatoire pour la CREATION');
                }
            }

            if (isset($params['proprietaireId']) && !empty($params['proprietaireId'])) {
                $postfields['proprietaireId'] = $params['proprietaireId'];
            }

            if (isset($params['medias']) && is_array($params['medias'])) {
                foreach ($params['medias'] as $k_media => $media) {
                    $postfields[$k_media] = $media;
                }
            }

            $fields = [];

            /*
                Traitement des données root, root.fieldList
            */
            if (isset($params['root'])) {
                $postfields['root'] = $params['root'];
                $fields[] = 'root';
            }
            if (isset($params['fieldlist'])) {
                $postfields['root.fieldList'] = $params['fieldlist'];
            } elseif (isset($params['fieldList'])) {
                $postfields['root.fieldList'] = $params['fieldList'];
            } elseif (isset($params['root.fieldlist'])) {
                $postfields['root.fieldList'] = $params['root.fieldlist'];
            } elseif (isset($params['root.fieldList'])) {
                $postfields['root.fieldList'] = $params['root.fieldList'];
            }


            /*
                Traitement des données aspect.XY.root, aspect.XY.root.fieldList
            */
            foreach ($params as $param_key => $param_value) {
                if (preg_match('#^aspect\.([0-9a-zA-Z-_]+)\.root$#', $param_key, $match)) {
                    $fields[] = $param_key;
                    $postfields[$param_key] = $param_value;

                    if (isset($params[$param_key . '.fieldList'])) {
                        $postfields[$param_key . '.fieldList'] = $params[$param_key . '.fieldList'];
                    }
                    if (isset($params[$param_key . '.removeAspectFields'])) {
                        $postfields[$param_key . '.removeAspectFields'] = $params[$param_key . '.removeAspectFields'];
                    }
                }
            }


            $postfields['fields'] = $fields;
        }

        foreach ($postfields as $k => $v) {
            if (preg_match('#(fields|fieldList|removeAspectFields)$#', $k)) {
                $postfields[$k] = json_encode($v);
            } elseif (preg_match('#root$#', $k)) {
                if (is_array($v) && sizeof($v) == 0) {
                    $postfields[$k] = "{}";
                } else {
                    $postfields[$k] = json_encode($v);
                }
            }
        }

        $this->lastPostfields = $postfields;

        $access_token = $this->gimme_token($this->projet_ecriture_clientId, $this->projet_ecriture_secret);

        $requestParams = [
            'token' => $access_token,
            'POSTFIELDS' => $postfields,
            'CUSTOMREQUEST' => 'PUT',
            'format' => 'json'
        ] ;

        if (isset($params['header']) && is_array($params['header'])) {
            $requestParams['header'] = $params['header'] ;
        }

        $result = $this->request('/api/v002/ecriture/', $requestParams);

        if (isset($result['id'])) {
            if (preg_match('#^[0-9]+$#', $result['id'])) {
                $this->last_id = $result['id'];
            } else {
                throw new \Exception('Lastid is not a number');
            }
        }

        if (isset($result['errorType'])) {
            throw new ApidaeException('ecriture_error', ApidaeException::INVALID_PARAMETER, $result);
        }

        /*
            Exemple d'erreur reçue : diffile de découper ça pour en récupérer une info lisible.
            Pas grand chose d'autre à faire que d'afficher l'erreur "brute".

            Erreur lors du traitement des données pour le paramètre 'root'. Cause: Unrecognized field "0"
                (class com.rhonealpestourisme.sitra.core.common.business.objettouristique.common.model.ouverture.PeriodeOuverture),
                not marked as ignorable
                    (14 known properties: "complementHoraire", "ouverturesJourDuMois", "tousLesAns", "identifiant", "nom", "ouverturesJournalieres", "dateDebut", "horaireFermeture", "type", "dateFin", "identifiantTechnique", "horaireOuverture", "ouverturesExceptionnelles", "identifiantTemporaire"])
                at [Source: {"type":"FETE_ET_MANIFESTATION","nom":{"libelleFr":"FMA TEST ApidaeEcriture 02\/03\/2021"},"localisation":{"adresse":{"adresse1":"Adresse 1","adresse2":"Adresse 2","adresse3":"Adresse 3","codePostal":"03400","commune":{"id":1555}}},"informationsFeteEtManifestation":{"portee":{"elementReferenceType":"FeteEtManifestationPortee","id":2354}},"contacts":[{"referent":true,"nom":"Nom contact 1","prenom":"Pr\u00e9nom contact 1","moyensCommunication":[{"type":{"id":204,"elementReferenceType":"MoyenCommunicationType"},"coordonnees":{"fr":"contact1@mail.fr"}},{"type":{"id":201,"elementReferenceType":"MoyenCommunicationType"},"coordonnees":{"fr":"01 02 03 04 05"}}]},{"nom":"Nom contact 2","prenom":"Pr\u00e9nom contact 2","moyensCommunication":[{"type":{"id":204,"elementReferenceType":"MoyenCommunicationType"},"coordonnees":{"fr":"contact2@mail.fr"}},{"type":{"id":201,"elementReferenceType":"MoyenCommunicationType"},"coordonnees":{"fr":"06 07 08 09 10"}}]}],"ouverture":{"periodesOuvertures":[{"dateDebut":"2021-03-02","dateFin":"2021-03-02","0":1614794098,"horaireOuverture":"11:00:00","horaireFermeture":"12:00:00","tousLesAns":false,"type":"OUVERTURE_SAUF"},{"dateDebut":"2021-03-09","dateFin":"2021-04-02","horaireOuverture":"16:00:00","horaireFermeture":"17:00:00","tousLesAns":false,"type":"OUVERTURE_SAUF"}]},"illustrations":[{"link":false,"type":"IMAGE","nom":{"libelleFr":"L\u00e9gende image 1"},"copyright":{"libelleFr":"Copyright image 1"},"traductionFichiers":[{"locale":"fr","url":"MULTIMEDIA#illustration-1"}]}]};
                 line: 1, column: 1058]
                 (through reference chain: com.rhonealpestourisme.sitra.core.common.api.business.objettouristique.model.FeteEtManifestationBean["ouverture"]->
                 com.rhonealpestourisme.sitra.core.common.business.objettouristique.common.model.ouverture.Ouverture["periodesOuvertures"]->java.util.HashSet[0]->
                 com.rhonealpestourisme.sitra.core.common.business.objettouristique.common.model.ouverture.PeriodeOuverture["0"])

            */

        return true;
    }

    /**
     * @deprecated 0.5.1
     */
    public function ajouter($params)
    {
        return $this->creation($params) ;
    }
    /**
     * @since 0.6.0
     */
    public function creation($params)
    {
        $params['action'] = 'CREATION';
        return $this->enregistrer($params);
    }

    /**
     * @deprecated 0.5.1
     */
    public function modifier($params)
    {
        return $this->modification($params) ;
    }
    /**
     * @since 0.6.0
     */
    public function modification($params)
    {
        $params['action'] = 'MODIFICATION';
        return $this->enregistrer($params);
    }

    /**
     * @since 0.6.0
     */
    public function annulationDemande($params)
    {
        $params['action'] = 'ANNULATION_DEMANDE';
        return $this->enregistrer($params);
    }

    /**
     * @deprecated 0.5.1
     */
    public function supprimer($params)
    {
        return $this->demandeSuppression($params) ;
    }

    /**
     * @since 0.6.0
     */
    public function demandeSuppression($params)
    {
        $params['action'] = 'DEMANDE_SUPPRESSION';
        return $this->enregistrer($params);
    }

    /**
     * @since 0.6.0
     */
    public function masquage($params)
    {
        $params['action'] = 'MASQUAGE';
        return $this->enregistrer($params);
    }

    /**
     * @since 0.6.0
     */
    public function demandeMasquage($params)
    {
        $params['action'] = 'DEMANDE_MASQUAGE';
        return $this->enregistrer($params);
    }

    /**
     * v002/autorisation/objet-touristique/modification/
     * Votre projet a-t-il les autorisations pour écrire sur cet objet ?
     * La recherche par tokenSSO permet de savoir si l'utilisateur authentifié (par son tokenSSO) pourra être désigné comme auteur des modifications
     *
     * @param int $id_offre Identifiant de l'offre sur laquelle vous souhaitez écrire
     * @param string $tokenSSO Token SSO d'une personne identifiée (optionnel)
     * @return	bool
     */
    public function autorisation(int $id_offre, string $tokenSSO = null)
    {
        $params = [
            'token' => $this->gimme_token(),
            'CUSTOMREQUEST' => 'GET'
        ];
        if ($tokenSSO != null) {
            $params['POSTFIELDS'] = http_build_query(['tokenSSO' => $tokenSSO]);
        }
        $result = $this->request('/api/v002/autorisation/objet-touristique/modification/' . $id_offre, $params);

        if (is_array($result) && isset($result['code'])) {
            if ($result['code'] != 200) {
                $this->lastAutorisation = 'HTTPCODE_' . $result['code'];
                return false;
            }
            if (isset($result['body'])) {
                $result['body'] = preg_replace('#"#', '', $result['body']);
            }

            $this->lastAutorisation = $result['body'];
            return $result['body'] == 'MODIFICATION_POSSIBLE';
        }
        $this->lastAutorisation = 'NO_HTTPCODE_RETURNED';
        return false;
    }

    public function lastAutorisationDetail()
    {
        return $this->lastAutorisation;
    }

    // http://dev.apidae-tourisme.com/fr/documentation-technique/v2/api-decriture/cas-particulier-des-multimedias
    // Méthode empruntée de https://github.com/guzzle/guzzle/blob/3a0787217e6c0246b457e637ddd33332efea1d2a/src/Guzzle/Http/Message/PostFile.php#L90
    public function getCurlValue($filePath, $contentType, $fileName)
    {
        // Disponible seulement en PHP >= 5.5
        if (function_exists('curl_file_create')) {
            return curl_file_create($filePath, $contentType, $fileName);
        }

        // Version compatible PHP < 5.5
        $value = "@{$filePath};filename=" . $fileName;
        if ($contentType) {
            $value .= ';type=' . $contentType;
        }

        return $value;
    }
}
