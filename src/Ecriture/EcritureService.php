<?php

declare(strict_types=1);

namespace PierreGranger\Ecriture;

use PierreGranger\ApidaeEcriture;
use PierreGranger\ApidaeException;

/**
 * API d'écriture : /api/v002/ecriture/ et /api/v002/autorisation/objet-touristique/modification/
 *
 * @see https://dev.apidae-tourisme.com/documentation-technique/v2/api-decriture/
 */
final class EcritureService
{
    /**
     * Format des clés de fichiers multimédias envoyés en multipart (référencés par MULTIMEDIA#<identifiant>).
     */
    public const MEDIA_KEY_PATTERN = '#^multimedia\.[A-Za-z0-9_-]+$#';

    public function __construct(
        private readonly ApidaeClient $client,
        private readonly bool $skipValidation = false,
        private readonly ?string $onValidationFail = null,
    ) {
    }

    /**
     * Envoie une demande d'écriture et renvoie la réponse de l'API (status, id…).
     *
     * @param array<string, mixed> $params voir ApidaeEcriture::enregistrer()
     * @return array<string, mixed>
     * @throws ApidaeException si l'API renvoie une erreur (errorType ou code HTTP hors 2xx)
     */
    public function enregistrer(array $params): array
    {
        $postfields = $this->buildPostfields($params);

        $requestParams = [
            'POSTFIELDS' => $postfields,
            'CUSTOMREQUEST' => 'PUT',
            'format' => 'json',
        ];

        if (isset($params['header']) && is_array($params['header'])) {
            $requestParams['header'] = $params['header'];
        }

        $result = $this->client->request('/api/v002/ecriture/', $requestParams);

        if (isset($result['errorType'])) {
            throw new ApidaeException('ecriture_error', ApidaeException::INVALID_PARAMETER, $result);
        }

        // Auparavant, une réponse en erreur sans errorType (401 OAuth, 5xx JSON…) était considérée comme un succès.
        $code = (int) ($result['code'] ?? 0);
        if ($code < 200 || $code >= 300) {
            throw new ApidaeException('ecriture_error', ApidaeException::INVALID_HTTPCODE, $result);
        }

        if (isset($result['id']) && !preg_match('#^[0-9]+$#', (string) $result['id'])) {
            throw new ApidaeException('Lastid is not a number', ApidaeException::INVALID_PARAMETER, $result);
        }

        return $result;
    }

    /**
     * Construit les champs multipart envoyés à /api/v002/ecriture/.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function buildPostfields(array $params): array
    {
        if (!isset($params['mode']) && isset($params['action'])) {
            $params['mode'] = $params['action'];
        }

        if (!isset($params['mode']) || !in_array($params['mode'], ApidaeEcriture::MODES, true)) {
            throw new \Exception('enregistrer_action_null');
        }

        $postfields = [];
        $postfields['mode'] = $params['mode'];

        if (in_array($postfields['mode'], ApidaeEcriture::ID_REQUIRED, true)) {
            if (isset($params['idFiche'])) {
                $postfields['id'] = $params['idFiche'];
            } elseif (isset($params['id'])) {
                $postfields['id'] = $params['id'];
            } else {
                throw new \Exception(__METHOD__ . ' : Identifiant de fiche non trouvé (id, idFiche ?)');
            }
        }

        // skipValidation doit être un string
        $skipValidation = isset($params['skipValidation']) ? (bool) $params['skipValidation'] : $this->skipValidation;
        $postfields['skipValidation'] = $skipValidation ? 'true' : 'false';

        if (isset($params['onValidationFail']) && in_array($params['onValidationFail'], ApidaeEcriture::ONVALIDATIONFAIL, true)) {
            $postfields['onValidationFail'] = $params['onValidationFail'];
        } elseif ($this->onValidationFail !== null && in_array($this->onValidationFail, ApidaeEcriture::ONVALIDATIONFAIL, true)) {
            $postfields['onValidationFail'] = $this->onValidationFail;
        }

        if (isset($params['tokenSSO'])) {
            $postfields['tokenSSO'] = $params['tokenSSO'];
        }

        if (in_array($postfields['mode'], ApidaeEcriture::FIELDS_REQUIRED, true)) {
            /**
             * Le paramètre "type" est obligatoire pour la création (ne pas confondre avec root={"type":"EQUIPEMENT"})
             * Auparavant le paramètre était récupéré de root.type mais ce n'était pas techniquement très juste
             */
            if ($postfields['mode'] == 'CREATION') {
                if (isset($params['type'])) {
                    $postfields['type'] = $params['type'];
                } elseif (isset($params['root']['type'])) { // Rétro compatibilité
                    $postfields['type'] = $params['root']['type'];
                } else {
                    throw new \Exception('Le paramètre "type" est obligatoire pour la CREATION');
                }
            }

            if (!empty($params['proprietaireId'])) {
                $postfields['proprietaireId'] = $params['proprietaireId'];
            }

            if (isset($params['medias']) && is_array($params['medias'])) {
                foreach ($params['medias'] as $k_media => $media) {
                    // Une clé libre pourrait écraser mode, id, tokenSSO… déjà validés
                    if (!is_string($k_media) || !preg_match(self::MEDIA_KEY_PATTERN, $k_media)) {
                        throw new \Exception(__METHOD__ . ' : clé de media invalide (attendu : multimedia.<identifiant>)');
                    }
                    if (!$media instanceof \CURLFile && !$media instanceof \CURLStringFile) {
                        throw new \Exception(__METHOD__ . ' : le media ' . $k_media . ' doit être un CURLFile (voir getCurlValue())');
                    }
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
            foreach (['fieldlist', 'fieldList', 'root.fieldlist', 'root.fieldList'] as $fieldListKey) {
                if (isset($params[$fieldListKey])) {
                    $postfields['root.fieldList'] = $params[$fieldListKey];
                    break;
                }
            }

            /*
                Traitement des données aspect.XY.root, aspect.XY.root.fieldList
            */
            foreach ($params as $param_key => $param_value) {
                if (is_string($param_key) && preg_match('#^aspect\.([0-9a-zA-Z_-]+)\.root$#', $param_key)) {
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
                $postfields[$k] = Response::json($v);
            } elseif (preg_match('#root$#', $k)) {
                $postfields[$k] = (is_array($v) && count($v) == 0) ? '{}' : Response::json($v);
            }
        }

        return $postfields;
    }

    /**
     * Votre projet a-t-il les autorisations pour écrire sur cet objet ?
     * Renvoie le détail brut : MODIFICATION_POSSIBLE, HTTPCODE_xxx, NO_HTTPCODE_RETURNED…
     */
    public function autorisation(int $id_offre, ?string $tokenSSO = null): string
    {
        $params = ['CUSTOMREQUEST' => 'GET'];
        if ($tokenSSO !== null) {
            $params['POSTFIELDS'] = http_build_query(['tokenSSO' => $tokenSSO]);
        }

        $result = $this->client->request('/api/v002/autorisation/objet-touristique/modification/' . $id_offre, $params);

        if (!isset($result['code'])) {
            return 'NO_HTTPCODE_RETURNED';
        }
        if ($result['code'] != 200) {
            return 'HTTPCODE_' . $result['code'];
        }

        return str_replace('"', '', (string) ($result['body'] ?? ''));
    }
}
