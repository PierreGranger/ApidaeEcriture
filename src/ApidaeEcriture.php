<?php

namespace PierreGranger;

use PierreGranger\ApidaeCore;

/**
 *
 * @author  Pierre Granger <pierre@pierre-granger.fr>
 *
 */

class ApidaeEcriture extends ApidaeCore
{

	public $skipValidation = 'false';

	public $statuts_api_ecriture = array('CREATION_VALIDATION_SKIPPED', 'CREATION_VALIDATION_ASKED', 'MODIFICATION_VALIDATION_SKIPPED', 'MODIFICATION_VALIDATION_ASKED', 'MODIFICATION_NO_DIFF', 'DEMANDE_SUPPRESSION_SENT', 'NO_ACTION');

	protected static $modes = array('CREATION', 'MODIFICATION', 'DEMANDE_SUPPRESSION');

	protected $_config;

	public $last_id = null;

	public $projet_ecriture_clientId;
	protected $projet_ecriture_secret;
	public $projet_ecriture_projectId;

	protected $lastAutorisation;

	public function __construct(array $params = null)
	{

		parent::__construct($params);

		if (!is_array($params)) throw new \Exception('$params is not an array');

		if (isset($params['projet_ecriture_clientId'])) $this->projet_ecriture_clientId = $params['projet_ecriture_clientId'];
		else throw new \Exception('missing projet_ecriture_clientId');
		if (isset($params['projet_ecriture_secret'])) $this->projet_ecriture_secret = $params['projet_ecriture_secret'];
		else throw new \Exception('missing projet_ecriture_secret');
		if (isset($params['projet_ecriture_projectId'])) $this->projet_ecriture_projectId = $params['projet_ecriture_projectId'];

		if (isset($params['skipValidation'])) $this->skipValidation = ($params['skipValidation']) ? true : false;
	}

	public function enregistrer($init_params = null)
	{

		if (!is_array($init_params)) {
			throw new \Exception('enregistrer_params_not_array');
			return false;
		}

		if (!isset($init_params['fieldlist'])) throw new \Exception('enregistrer_fieldlist_null');
		if (!isset($init_params['root'])) throw new \Exception('enregistrer_root_null');
		if (!isset($init_params['action']) || !in_array($init_params['action'], self::$modes)) throw new \Exception('enregistrer_action_null');

		$fieldlist = $init_params['fieldlist'];
		$root = $init_params['root'];
		$medias = isset($init_params['medias']) ? $init_params['medias'] : null;
		$proprietaireId = isset($init_params['proprietaireId']) && !empty($init_params['proprietaireId']) ? $init_params['proprietaireId'] : null;
		$clientId = isset($init_params['clientId']) ? $init_params['clientId'] : null;
		$secret = isset($init_params['secret']) ? $init_params['secret'] : null;
		$action = $init_params['action'];
		$idFiche = isset($init_params['idFiche']) ? $init_params['idFiche'] : null;
		$token = isset($init_params['token']) ? $init_params['token'] : null;

		$ko = array();

		$fields = array('root');
		$params = array();

		if (!in_array($action, self::$modes)) {
			throw new \Exception('Action ' . $action . ' invalide');
			return false;
		}

		$params['mode'] = $action;
		if ($params['mode'] == 'MODIFICATION' || $params['mode'] == 'DEMANDE_SUPPRESSION') {
			$params['id'] = $idFiche;
		}
		$params['skipValidation'] = $this->skipValidation ? 'true' : 'false';

		if ($params['mode'] != 'DEMANDE_SUPPRESSION') {
			$params['type'] = $root['type'];
			$params['root'] = json_encode($root);
			$params['fields'] = json_encode($fields);
			$params['root.fieldList'] = json_encode($fieldlist);

			if (!empty($proprietaireId))
				$params['proprietaireId'] = $proprietaireId;

			if (isset($medias) && is_array($medias))
				foreach ($medias as $k_media => $media)
					$params[$k_media] = $media;
		}

		$access_token = $this->gimme_token($clientId, $secret);

		$result = $this->request('/api/v002/ecriture/', array(
			'token' => $access_token,
			'POSTFIELDS' => $params,
			'CUSTOMREQUEST' => 'PUT',
			'format' => 'json'
		));

		if (isset($result['array']['id'])) {
			if (preg_match('#^[0-9]+$#', $result['array']['id'])) {
				$this->last_id = $result['array']['id'];
			} else throw new \Exception('Lastid is not a number');
		}

		if (isset($result['array']['errorType'])) {
			$ko[] = __LINE__ . $result['array']['errorType'];
			$ko[] = __LINE__ . $result['array']['message'];
			throw new ApidaeException('ecriture_error', ApidaeException::INVALID_PARAMETER, array(
				//'debug' => $this->debug,
				'result' => $result
			));
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

		if (sizeof($ko) > 0) {
			return $ko;
		}
		return true;
	}

	public function ajouter($params)
	{
		// $fieldlist,$root,$medias=null,$clientId=null,$secret=null,$token=null
		// $fieldlist,$root,$medias,$clientId,$secret,$action='CREATION',null,$token
		$params['action'] = 'CREATION';
		return $this->enregistrer($params);
	}

	public function modifier($params)
	{
		// $fieldlist,$root,$idFiche,$medias=null,$clientId=null,$secret=null,$token=null
		// $fieldlist,$root,$medias,$clientId,$secret,$action='MODIFICATION',$idFiche,$token
		$params['action'] = 'MODIFICATION';
		return $this->enregistrer($params);
	}

	public function supprimer($params)
	{
		// $idFiche,$clientId=null,$secret=null,$token=null
		// null,null,null,$clientId,$secret,$action='DEMANDE_SUPPRESSION',$idFiche,$token
		$params['action'] = 'DEMANDE_SUPPRESSION';
		return $this->enregistrer($params);
	}

	public function enregistrerDonneesPrivees($idFiche, $cle, $valeur, $lng = 'fr')
	{
		$donneesPrivees = array('objetsTouristiques' => array());

		/* Pour chaque objet touristique à modifer on peut avoir 1 ou plusieurs descriptifs privés à modifier. On va les stocker dans $descriptifsPrives. */
		$descriptifsPrives = array();

		$descriptifsPrives[] = array(
			'nomTechnique' => $cle,
			'descriptif' => array(
				'libelle' . ucfirst($lng) => $valeur
			)
		);

		/* Pour chaque objet à modifier on ajoute une entrée dans $donneesPrivees['objetsTouristiques'] */
		$donneesPrivees['objetsTouristiques'][] = array(
			'id' => $idFiche,
			'donneesPrivees' => $descriptifsPrives
		);

		/* On a construit notre tableau en php : on l'encode en json pour l'envoyer à l'API. */
		$POSTFIELDS = array('donneesPrivees' => json_encode($donneesPrivees));

		$access_token = $this->gimme_token();

		$result = $this->request('/api/v002/donnees-privees/', array(
			'token' => $access_token,
			'POSTFIELDS' => $POSTFIELDS,
			'CUSTOMREQUEST' => 'PUT',
			'format' => 'json'
		));

		$json_result = $result['object'];

		if ($json_result->status == 'MODIFICATION_DONNEES_PRIVEES') {
			return true;
		} else
			return $json_result->status . ' - ' . $json_result->message;

		return true;
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
		$result = $this->request('/api/v002/autorisation/objet-touristique/modification/' . $id_offre, array(
			'token' => $this->gimme_token()
		));
		if (is_array($result) && isset($result['code'])) {
			if ($result['code'] != 200) {
				$this->lastAutorisation = 'HTTPCODE_' . $result['code'];
				return false;
			}
			if (isset($result['body'])) $result['body'] = preg_replace('#"#', '', $result['body']);

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
