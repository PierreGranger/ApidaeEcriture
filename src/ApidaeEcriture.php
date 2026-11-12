<?php

namespace PierreGranger;

use PierreGranger\ApidaeCore;
use PierreGranger\Ecriture\ApidaeClient;
use PierreGranger\Ecriture\ClosureClient;
use PierreGranger\Ecriture\CollaborateursService;
use PierreGranger\Ecriture\CriteresInternesService;
use PierreGranger\Ecriture\DonneesPriveesService;
use PierreGranger\Ecriture\EcritureService;

/**
 * Façade historique de l'API d'écriture Apidae.
 *
 * La logique est portée par des services autonomes (PierreGranger\Ecriture\*) qui ne dépendent que de
 * l'interface ApidaeClient. Cette classe hérite toujours de ApidaeCore pour la compatibilité
 * (alerte(), setTimeout(), lastResult()… sont utilisés par les projets existants) et se contente de
 * déléguer aux services.
 *
 * @author  Pierre Granger <pierre@pierre-granger.fr>
 */
class ApidaeEcriture extends ApidaeCore
{
    protected bool $skipValidation = false;
    protected ?string $onValidationFail = null;

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

    /**
     * Identifiant renvoyé par le dernier appel à enregistrer() (null si l'appel a échoué ou n'a pas renvoyé d'id).
     */
    public ?string $last_id = null;

    // projet_ecriture_clientId (public) et projet_ecriture_secret (protected) sont déclarés dans ApidaeCore :
    // ils ne sont plus redéclarés ici, ce qui rendait le secret public.
    public ?int $projet_ecriture_projectId = null;

    protected ?string $lastAutorisation = null;

    private ?ApidaeClient $client = null;

    public function __construct(?array $params)
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
            $this->skipValidation = (bool) $params['skipValidation'];
        }

        if (isset($params['onValidationFail']) && in_array($params['onValidationFail'], self::ONVALIDATIONFAIL, true)) {
            $this->onValidationFail = $params['onValidationFail'];
        }
    }

    /**
     * Client authentifié avec le token du projet d'écriture, passé aux services.
     * Les closures appellent $this->request() : une sous-classe qui surcharge request() reste prise en compte.
     */
    protected function client(): ApidaeClient
    {
        return $this->client ??= new ClosureClient(
            fn (string $path, array $params): array => $this->request($path, $params),
            fn (): string => $this->gimme_token($this->projet_ecriture_clientId, $this->projet_ecriture_secret),
            fn () => $this->forgetToken($this->projet_ecriture_clientId),
        );
    }

    protected function ecritureService(): EcritureService
    {
        return new EcritureService($this->client(), $this->skipValidation, $this->onValidationFail);
    }

    /**
     * @param array<string, mixed>|null $params mode, id|idFiche, type, root, fieldList, aspect.XY.root, medias, tokenSSO, skipValidation…
     * @throws ApidaeException si l'API renvoie une erreur (errorType ou code HTTP hors 2xx)
     */
    public function enregistrer(?array $params = null): bool
    {
        if (!is_array($params)) {
            throw new \Exception('enregistrer_params_not_array');
        }

        $this->last_id = null;

        $result = $this->ecritureService()->enregistrer($params);

        if (isset($result['id'])) {
            $this->last_id = (string) $result['id'];
        }

        return true;
    }

    /**
     * @deprecated 0.5.1
     */
    public function ajouter(array $params): bool
    {
        return $this->creation($params) ;
    }
    /**
     * @since 0.6.0
     */
    public function creation(array $params): bool
    {
        $params['mode'] = 'CREATION';
        return $this->enregistrer($params);
    }

    /**
     * @deprecated 0.5.1
     */
    public function modifier(array $params): bool
    {
        return $this->modification($params) ;
    }
    /**
     * @since 0.6.0
     */
    public function modification(array $params): bool
    {
        $params['mode'] = 'MODIFICATION';
        return $this->enregistrer($params);
    }

    /**
     * @since 0.6.0
     */
    public function annulationDemande(array $params): bool
    {
        $params['mode'] = 'ANNULATION_DEMANDE';
        return $this->enregistrer($params);
    }

    /**
     * @deprecated 0.5.1
     */
    public function supprimer(array $params): bool
    {
        return $this->demandeSuppression($params) ;
    }

    /**
     * @since 0.6.0
     */
    public function demandeSuppression(array $params): bool
    {
        $params['mode'] = 'DEMANDE_SUPPRESSION';
        return $this->enregistrer($params);
    }

    /**
     * @since 0.6.0
     */
    public function masquage(array $params): bool
    {
        $params['mode'] = 'MASQUAGE';
        return $this->enregistrer($params);
    }

    /**
     * @since 0.6.0
     */
    public function demandeMasquage(array $params): bool
    {
        $params['mode'] = 'DEMANDE_MASQUAGE';
        return $this->enregistrer($params);
    }

    /**
     * v002/autorisation/objet-touristique/modification/
     * Votre projet a-t-il les autorisations pour écrire sur cet objet ?
     * La recherche par tokenSSO permet de savoir si l'utilisateur authentifié (par son tokenSSO) pourra être désigné comme auteur des modifications
     *
     * @param int $id_offre Identifiant de l'offre sur laquelle vous souhaitez écrire
     * @param string|null $tokenSSO Token SSO d'une personne identifiée (optionnel)
     */
    public function autorisation(int $id_offre, ?string $tokenSSO = null): bool
    {
        $this->lastAutorisation = $this->ecritureService()->autorisation($id_offre, $tokenSSO);
        return $this->lastAutorisation === 'MODIFICATION_POSSIBLE';
    }

    public function lastAutorisationDetail(): ?string
    {
        return $this->lastAutorisation;
    }

    /**
     * Prépare un fichier à envoyer dans $params['medias'] (clé multimedia.<identifiant>).
     *
     * @see https://dev.apidae-tourisme.com/fr/documentation-technique/v2/api-decriture/cas-particulier-des-multimedias
     */
    public function getCurlValue(string $filePath, ?string $contentType, string $fileName): \CURLFile
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \Exception(__METHOD__ . ' : fichier introuvable ou illisible');
        }

        return new \CURLFile($filePath, $contentType, $fileName);
    }

    /*
     * Collaborateurs
     */

    public function putCollaborateurs(int $id, array $membresCollaborateurs, ?string $tokenSSO = null): bool
    {
        (new CollaborateursService($this->client()))->collaborateurs('PUT', $id, $membresCollaborateurs, $tokenSSO);
        return true;
    }

    public function deleteCollaborateurs(int $id, array $membresCollaborateurs, ?string $tokenSSO = null): bool
    {
        (new CollaborateursService($this->client()))->collaborateurs('DELETE', $id, $membresCollaborateurs, $tokenSSO);
        return true;
    }

    /*
     * Données privées
     */

    /**
     * @see https://dev.apidae-tourisme.com/documentation-technique/v2/api-decriture/v002donnees-privees/
     *
     * @param string $method PUT|DELETE
     * @param array<string, string>|null $valeurs ['fr' => 'Valeur FR', 'en' => 'Valeur EN']
     */
    public function descriptifsPrives(string $method, int $idFiche, string $cle, ?array $valeurs, ?string $tokenSSO = null): bool
    {
        (new DonneesPriveesService($this->client()))->descriptifsPrives($method, $idFiche, $cle, $valeurs, $tokenSSO);
        return true;
    }

    /**
     * @param string $method PUT|DELETE
     * @param array<string, mixed> $payload ['objetsTouristiques' => [...]]
     */
    public function donneesPrivees(string $method, array $payload, ?string $tokenSSO = null): bool
    {
        (new DonneesPriveesService($this->client()))->donneesPrivees($method, $payload, $tokenSSO);
        return true;
    }

    /**
     * @deprecated 0.6.1
     */
    public function enregistrerDonneesPrivees(int $idFiche, string $cle, array $valeurs, ?string $tokenSSO = null): bool
    {
        return $this->descriptifsPrives('PUT', $idFiche, $cle, $valeurs, $tokenSSO) ;
    }
    public function putDescriptifsPrives(int $idFiche, string $cle, array $valeurs, ?string $tokenSSO = null): bool
    {
        return $this->descriptifsPrives('PUT', $idFiche, $cle, $valeurs, $tokenSSO) ;
    }

    /**
     * @deprecated 0.6.1
     */
    public function supprimerDonneesPrivees(int $idFiche, string $cle, ?string $tokenSSO = null): bool
    {
        return $this->descriptifsPrives('DELETE', $idFiche, $cle, null, $tokenSSO) ;
    }
    public function deleteDescriptifsPrives(int $idFiche, string $cle, ?string $tokenSSO = null): bool
    {
        return $this->descriptifsPrives('DELETE', $idFiche, $cle, null, $tokenSSO) ;
    }

    /*
     * Critères internes
     */

    /**
     * @see https://dev.apidae-tourisme.com/fr/documentation-technique/v2/api-decriture/v002criteres-internes
     *
     * Renvoie seulement true ou false :
     * les erreurs sont à traiter en récupérant les exception ou en analysant le contenu de ApidaeEcriture::lastResult() si le retour est false.
     */
    public function critereInterne(string $method, array $id_offres, array $id_criteres): bool
    {
        $ret = (new CriteresInternesService($this->client()))->critereInterne($method, $id_offres, $id_criteres);

        if ($ret['warning'] !== null && is_array($this->lastResult)) {
            $this->lastResult['warning'] = $ret['warning'];
        }

        return $ret['ok'];
    }
}
