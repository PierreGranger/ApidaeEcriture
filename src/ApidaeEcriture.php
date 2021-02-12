<?php

	namespace PierreGranger ;
/**
*
* @author  Pierre Granger <pierre@pierre-granger.fr>
*
*/

	class ApidaeEcriture extends ApidaeCore {

		public $skipValidation = 'false' ;

		public $statuts_api_ecriture = Array('CREATION_VALIDATION_SKIPPED','CREATION_VALIDATION_ASKED','MODIFICATION_VALIDATION_SKIPPED','MODIFICATION_VALIDATION_ASKED','MODIFICATION_NO_DIFF','DEMANDE_SUPPRESSION_SENT','NO_ACTION') ;

		protected static $modes = Array('CREATION','MODIFICATION','DEMANDE_SUPPRESSION') ;

		protected $_config ;

		public $last_id = null ;

		protected $projet_ecriture_clientId ;
		protected $projet_ecriture_secret ;
		protected $projet_ecriture_projectId ;

		public function __construct(array $params=null) {
			
			parent::__construct($params) ;

			if ( ! is_array($params) ) throw new \Exception('$params is not an array') ;
			
			if ( isset($params['projet_ecriture_clientId']) ) $this->projet_ecriture_clientId = $params['projet_ecriture_clientId'] ; else throw new \Exception('missing projet_ecriture_clientId') ;
			if ( isset($params['projet_ecriture_secret']) ) $this->projet_ecriture_secret = $params['projet_ecriture_secret'] ; else throw new \Exception('missing projet_ecriture_secret') ;
			if ( isset($params['projet_ecriture_projectId']) ) $this->projet_ecriture_projectId = $params['projet_ecriture_projectId'] ;

			if ( isset($params['skipValidation']) ) $this->skipValidation = ( $params['skipValidation'] ) ? true : false ;

			
		}

		public function enregistrer($init_params=null) {

			if ( ! is_array($init_params) )
			{
				throw new \Exception('enregistrer_params_not_array') ;
				return false ;
			}

			if ( ! isset($init_params['fieldlist']) ) throw new \Exception('enregistrer_fieldlist_null') ;
			if ( ! isset($init_params['root']) ) throw new \Exception('enregistrer_root_null') ;
			if ( ! isset($init_params['action']) || ! in_array($init_params['action'],self::$modes) ) throw new \Exception('enregistrer_action_null') ;

			$fieldlist = $init_params['fieldlist'] ;
			$root = $init_params['root'] ;
			$medias = isset($init_params['medias']) ? $init_params['medias'] : null ;
			$proprietaireId = isset($init_params['proprietaireId']) && ! empty($init_params['proprietaireId']) ? $init_params['proprietaireId'] : null ;
			$clientId = isset($init_params['clientId']) ? $init_params['clientId'] : null ;
			$secret = isset($init_params['secret']) ? $init_params['secret'] : null ;
			$action = $init_params['action'] ;
			$idFiche = isset($init_params['idFiche']) ? $init_params['idFiche'] : null ;
			$token = isset($init_params['token']) ? $init_params['token'] : null ;

			$ko = Array() ;

			$fields = Array('root') ;
			$params = Array() ;

			if ( ! in_array($action,self::$modes) )
			{
				throw new \Exception('Action '.$action.' invalide') ;	
				return false ;
			}
			
			$params['mode'] = $action ;
			if ($params['mode']=='MODIFICATION' || $params['mode']=='DEMANDE_SUPPRESSION') {
				$params['id'] = $idFiche;
			}
			$params['skipValidation'] = $this->skipValidation ? 'true' : 'false' ;

			if ($params['mode'] != 'DEMANDE_SUPPRESSION')
			{
				$params['type'] = $root['type'] ;
				$params['root'] = json_encode($root) ;
				$params['fields'] = json_encode($fields) ;
				$params['root.fieldList'] = json_encode($fieldlist) ;

				if (!empty($proprietaireId))
					$params['proprietaireId'] = $proprietaireId;

				if ( isset($medias) && is_array($medias) )
					foreach ( $medias as $k_media => $media )
						$params[$k_media] = $media ;
			}
			
			$this->debug($fieldlist,'$fieldList') ;
			$this->debug($root,'$root') ;
			$this->debug($params,'$params') ;

			try {
				$access_token = $this->gimme_token($clientId,$secret) ;
				$this->debug($access_token,'$access_token') ;

				if ( ! $access_token )
				{
					throw new \Exception(__LINE__.'Impossible de récupérer le token d\'écriture') ;
				}
			}
			catch(\Exception $e) {
				$msg = sprintf( 'Curl failed with error #%d: %s', $e->getCode(), $e->getMessage() ) ;
				if ( $this->debug ) echo '<div class="alert alert-warning">'.$msg.'</div>' ;
				return Array('errorCode'=>$e->getCode(),'message'=>$e->getMessage()) ;
			}
			
			$method = 'curl' ;

			if ( class_exists('\Sitra\ApiClient\Client') && $this->debug )
				$method = 'tractopelle' ;

			if ( $method == 'tractopelle' )
			{
				$client = new \Sitra\ApiClient\Client([
				    'ssoClientId'    => $clientId,
				    'ssoSecret'      => $secret
				]);
				
				$client->ecrire($params);
			}
			elseif ( $method == 'curl' )
			{

				try {
					
					$ch = curl_init();
					
					curl_setopt($ch,CURLOPT_URL, $this->url_api().'api/v002/ecriture/');
					
					$header = Array() ;
					$header[] = "Authorization: Bearer ".$access_token ;
					curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
					curl_setopt($ch,CURLOPT_POSTFIELDS, ($params));
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
					// http://dev.apidae-tourisme.com/fr/documentation-technique/v2/oauth/authentification-avec-un-token-oauth2
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
					curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
					
					$result = curl_exec($ch);
				
					if (FALSE === $result) throw new \Exception(curl_error($ch), curl_errno($ch));
					
					$result = json_decode($result,true) ;
					if ( isset($result['id']) )
						$this->last_id = $result['id'] ;
					
				} catch(\Exception $e) {
					$msg = sprintf( 'Curl failed with error #%d: %s', $e->getCode(), $e->getMessage() ) ;
					if ( $this->debug ) echo '<div class="alert alert-warning">'.$msg.'</div>' ;
				}
				
				curl_close($ch);

			}

			$this->debug($result,'$result') ;
			
			if ( ! is_array($result) )
			{
				$ko[] = __LINE__.$result ;
			}
			elseif ( isset($result['errorType']) )
			{
				$ko[] = __LINE__.$result['errorType'] ;
				$ko[] = __LINE__.$result['message'] ;
			}

			if ( sizeof($ko) > 0 )
			{
				return $ko ;
			}
			return true ;
		}

		public function ajouter($params) {
			// $fieldlist,$root,$medias=null,$clientId=null,$secret=null,$token=null
			// $fieldlist,$root,$medias,$clientId,$secret,$action='CREATION',null,$token
			$params['action'] = 'CREATION' ;
			return $this->enregistrer($params);
		}

		public function modifier($params) {
			// $fieldlist,$root,$idFiche,$medias=null,$clientId=null,$secret=null,$token=null
			// $fieldlist,$root,$medias,$clientId,$secret,$action='MODIFICATION',$idFiche,$token
			$params['action'] = 'MODIFICATION' ;
			return $this->enregistrer($params);
		}

		public function supprimer($params) {
			// $idFiche,$clientId=null,$secret=null,$token=null
			// null,null,null,$clientId,$secret,$action='DEMANDE_SUPPRESSION',$idFiche,$token
			$params['action'] = 'DEMANDE_SUPPRESSION' ;
			return $this->enregistrer($params);
		}
		
		public function enregistrerDonneesPrivees($idFiche,$cle,$valeur,$lng='fr')
		{
			$donneesPrivees = Array('objetsTouristiques'=>Array()) ;

			/* Pour chaque objet touristique à modifer on peut avoir 1 ou plusieurs descriptifs privés à modifier. On va les stocker dans $descriptifsPrives. */
			$descriptifsPrives = Array() ;

			$descriptifsPrives[] = Array(
				'nomTechnique' => $cle,
				'descriptif' => Array(
					'libelle'.ucfirst($lng) => $valeur
				)
			) ;

			/* Pour chaque objet à modifier on ajoute une entrée dans $donneesPrivees['objetsTouristiques'] */
			$donneesPrivees['objetsTouristiques'][] = Array(
				'id' => $idFiche,
				'donneesPrivees' => $descriptifsPrives
			) ;

			/* On a construit notre tableau en php : on l'encode en json pour l'envoyer à l'API. */
			$POSTFIELDS = Array('donneesPrivees'=>json_encode($donneesPrivees)) ;

			try {
				$access_token = $this->gimme_token() ;
				$this->debug($access_token,'$access_token') ;

				if ( ! isset($access_token) )
				{
					throw new \Exception(__LINE__.'Le token d\'écriture n\'a pas pu être récupéré') ;	
				}
			}
			catch(\Exception $e) {
				$msg = sprintf( 'Curl failed with error #%d: %s', $e->getCode(), $e->getMessage() ) ;
				if ( $this->debug ) echo '<div class="alert alert-warning">'.$msg.'</div>' ;
				return Array('errorCode'=>$e->getCode(),'message'=>$e->getMessage()) ;
			}

			try {

				$ch = curl_init() ;
				curl_setopt($ch,CURLOPT_URL, $this->url_api().'api/v002/donnees-privees/');
				$header = Array() ;
				$header[] = "Authorization: Bearer ".$access_token ;
				curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $POSTFIELDS);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
				curl_setopt($ch, CURLOPT_ENCODING, 'UTF-8');
				
				$result = curl_exec($ch);
			
				if (FALSE === $result) throw new \Exception(curl_error($ch), curl_errno($ch));
				
				$json_result = json_decode($result) ;
				$is_json =  ( json_last_error() == JSON_ERROR_NONE ) ;
				
				if ( ! $is_json )
				{
					return false ;
				}

				if ( $json_result->status == 'MODIFICATION_DONNEES_PRIVEES' )
				{
					return true ;
				}
				else
					return $json_result->status.' - '.$json_result->message ;

				curl_close($ch);

				return true ;
				
			} catch(\Exception $e) {

				trigger_error(sprintf(
					'Curl failed with error #%d: %s',
					$e->getCode(), $e->getMessage()),
					E_USER_ERROR);

			}
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
