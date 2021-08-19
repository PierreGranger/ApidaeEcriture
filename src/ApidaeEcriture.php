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

	public $skipValidation = false;

	public $statuts_api_ecriture = array('CREATION_VALIDATION_SKIPPED', 'CREATION_VALIDATION_ASKED', 'MODIFICATION_VALIDATION_SKIPPED', 'MODIFICATION_VALIDATION_ASKED', 'MODIFICATION_NO_DIFF', 'DEMANDE_SUPPRESSION_SENT', 'NO_ACTION');

	protected const MODES = array('CREATION', 'MODIFICATION', 'DEMANDE_SUPPRESSION');

	protected $_config;

	public $last_id = null;

	public $projet_ecriture_clientId;
	protected $projet_ecriture_secret;
	public $projet_ecriture_projectId;

	protected $lastAutorisation;

	protected $lastResult;
	protected $lastPostfields;

	public function __construct(array $params = null)
	{

		parent::__construct($params);

		if (!is_array($params)) throw new \Exception('$params is not an array');

		if (isset($params['projet_ecriture_clientId'])) $this->projet_ecriture_clientId = $params['projet_ecriture_clientId'];
		else throw new \Exception('missing projet_ecriture_clientId');
		if (isset($params['projet_ecriture_secret'])) $this->projet_ecriture_secret = $params['projet_ecriture_secret'];
		else throw new \Exception('missing projet_ecriture_secret');
		if (isset($params['projet_ecriture_projectId'])) $this->projet_ecriture_projectId = $params['projet_ecriture_projectId'];

		if (isset($params['skipValidation'])) $this->skipValidation = $params['skipValidation'] ? true : false;
	}

	public function enregistrer($params = null)
	{
		if (!is_array($params)) throw new \Exception('enregistrer_params_not_array');

		if (!isset($params['action']) || !in_array($params['action'], self::MODES)) throw new \Exception('enregistrer_action_null');
		if (!in_array($params['action'], self::MODES)) throw new \Exception(__METHOD__ . ' : Action ' . $params['action'] . ' invalide');
		$action = $params['action'];

		$token = isset($params['token']) ? $params['token'] : null;

		$ko = [];
		$postfields = [];

		$postfields['mode'] = $action;
		if ($postfields['mode'] == 'MODIFICATION' || $postfields['mode'] == 'DEMANDE_SUPPRESSION') {
			if (isset($params['idFiche'])) $postfields['id'] = $params['idFiche'];
			elseif (isset($params['id'])) $postfields['id'] = $params['id'];
			else throw new \Exception(__METHOD__ . ' : Identifiant de fiche non trouvé (id, idFiche ?)');
		}

		// skipValidation doit être un string
		if (isset($params['skipValidation'])) $postfields['skipValidation'] = $params['skipValidation'] ? 'true' : 'false';
		else $postfields['skipValidation'] = $this->skipValidation ? 'true' : 'false';

		if (isset($params['tokenSSO'])) $postfields['tokenSSO'] = $params['tokenSSO'];

		if ($postfields['mode'] != 'DEMANDE_SUPPRESSION') {

			/**
			 * Le paramètre "type" est obligatoire pour la création (ne pas confondre avec root={"type":"EQUIPEMENT"}) 
			 * Auparavant le paramètre était récupéré de root.type mais ce n'était pas techniquement très juste
			 */
			if ($postfields['mode'] == 'CREATION') {
				if (isset($params['type'])) $postfields['type'] = $params['type'];
				// Rétro compatibilité
				elseif (isset($params['root']['type'])) $postfields['type'] = $params['root']['type'];
				else throw new \Exception('Le paramètre "type" est obligatoire pour la CREATION');
			}

			if (isset($params['proprietaireId']) && !empty($params['proprietaireId']))
				$postfields['proprietaireId'] = $params['proprietaireId'];

			if (isset($params['medias']) && is_array($params['medias']))
				foreach ($params['medias'] as $k_media => $media)
					$postfields[$k_media] = $media;

			$fields = [];

			/*
				Traitement des données root, root.fieldList
			*/
			if (isset($params['root'])) {
				$postfields['root'] = $params['root'];
				$fields[] = 'root';
			}
			if (isset($params['fieldlist'])) $postfields['root.fieldList'] = $params['fieldlist'];
			elseif (isset($params['fieldList'])) $postfields['root.fieldList'] = $params['fieldList'];
			elseif (isset($params['root.fieldlist'])) $postfields['root.fieldList'] = $params['root.fieldlist'];
			elseif (isset($params['root.fieldList'])) $postfields['root.fieldList'] = $params['root.fieldList'];


			/*
				Traitement des données aspect.XY.root, aspect.XY.root.fieldList
			*/
			$aspects = [];
			foreach ($params as $param_key => $param_value) {
				if (preg_match('#^aspect\.([0-9a-zA-Z-_]+)\.root$#', $param_key, $match)) {
					$aspects[] = $match[1];
					$fields[] = $param_key;
					$postfields[$param_key] = $param_value;
					if (isset($params[$param_key . '.fieldList']))
						$postfields[$param_key . '.fieldList'] = $params[$param_key . '.fieldList'];
				}
			}


			$postfields['fields'] = $fields;
		}

		foreach ($postfields as $k => $v) {
			if (preg_match('#(fields|root|fieldList)$#', $k)) {
				$postfields[$k] = json_encode($v);
			}
		}
		$this->lastPostfields = $postfields;

		$access_token = $this->gimme_token($this->projet_ecriture_clientId, $this->projet_ecriture_secret);



		$result = $this->request('/api/v002/ecriture/', array(
			'token' => $access_token,
			'POSTFIELDS' => $postfields,
			'CUSTOMREQUEST' => 'PUT',
			'format' => 'json'
		));

		$this->lastResult = $result;

		if (isset($result['array']['id'])) {
			if (preg_match('#^[0-9]+$#', $result['array']['id'])) {
				$this->last_id = $result['array']['id'];
			} else throw new \Exception('Lastid is not a number');
		}

		if (isset($result['array']['errorType'])) {
			$ko[] = __LINE__ . $result['array']['errorType'];
			$ko[] = __LINE__ . $result['array']['message'];
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
		$params = [
			'token' => $this->gimme_token(),
			'CUSTOMREQUEST' => 'GET'
		];
		if ($tokenSSO != null) $params['POSTFIELDS'] = http_build_query(['tokenSSO' => $tokenSSO]);
		$result = $this->request('/api/v002/autorisation/objet-touristique/modification/' . $id_offre, $params);

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

	/**
	 * Renvoie le détail de $result de ApidaeCore::request
	 * En cas de retour correct, renvoie un tableau :
	 * [
	 * 	'code' => 200,
	 * 	'header' => 'HTTP/1.1 100 Continue...',
	 * 	'body' => '{"status":"MODIFICATION_VALIDATION_ASKED"}',
	 * 	'object' => {
	 * 		'status' => 'MODIFICATION_VALIDATION_ASKED'
	 * 	}
	 * 	'array' => [
	 * 		'status' => 'MODIFICATION_VALIDATION_ASKED'
	 * 	]
	 * ]
	 * 
	 * Exemple erreur
	 * [
	 * 	'body' => '{"message":"Cet objet est déjà en cours de modification","errorType":"ECRITURE_FORBIDDEN}',
	 * 	'object' => {
	 * 		'message' => 'Cet objet...',
	 * 		'errorType' => 'ECRITURE_FORBIDDEN,
	 * 	},
	 * 	'array' => [
	 * 		'message' => 'Cet objet...',
	 * 		'errorType' => 'ECRITURE_FORBIDDEN,
	 * 	]
	 * ]
	 */
	public function lastResult()
	{
		return $this->lastResult;
	}

	public function lastPostfields()
	{
		return $this->lastPostfields;
	}
}
