<?php

	namespace PierreGranger ;
/*
*
* @author  Pierre Granger <pierre@pierre-granger.fr>
* @version    0.1.5
*
*/

	class ApidaeEcriture {

		private static $url_api = Array(
			'preprod' => 'http://api.sitra2-vm-preprod.accelance.net/',
			'prod' => 'http://api.apidae-tourisme.com/'
		) ;

		private static $url_base = Array(
				'preprod' => 'http://sitra2-vm-preprod.accelance.net/',
				'prod' => 'https://base.apidae-tourisme.com/'
		) ;

		private $type_prod = 'prod' ;

		public $skipValidation = 'false' ;

		public $debug ;

		public $statuts_api_ecriture = Array('CREATION_VALIDATION_SKIPPED','CREATION_VALIDATION_ASKED','MODIFICATION_VALIDATION_SKIPPED','MODIFICATION_VALIDATION_ASKED','MODIFICATION_NO_DIFF','DEMANDE_SUPPRESSION_SENT','NO_ACTION') ;

		private static $modes = Array('CREATION','MODIFICATION','DEMANDE_SUPPRESSION') ;

		private $_config ;

		public $debugTime = false ;

		public $last_id = null ;

		private $projet_ecriture_clientId ;
		private $projet_ecriture_secret ;

		public function __construct($params=null) {
			
			if ( $this->debugTime ) $start = microtime(true) ;

			if ( ! is_array($params) ) throw new \Exception('$params is not an array') ;

			if ( isset($params['debug']) && $params['debug'] == true ) $this->debug = $params['debug'] ; else $this->debug = false ;

			if ( isset($params['type_prod']) && in_array($params['type_prod'],Array('prod','preprod')) ) $this->type_prod = $params['type_prod'] ;

			if ( isset($params['projet_ecriture_clientId']) ) $this->projet_ecriture_clientId = $params['projet_ecriture_clientId'] ; else throw new \Exception('missing projet_ecriture_clientId') ;
			if ( isset($params['projet_ecriture_secret']) ) $this->projet_ecriture_secret = $params['projet_ecriture_secret'] ; else throw new \Exception('missing projet_ecriture_secret') ;

			if ( isset($params['skipValidation']) ) $this->skipValidation = ( $params['skipValidation'] ) ? true : false ;

			$this->_config = $params ;

			if ( $this->debugTime ) $this->debug('construct '.(microtime(true)-$start)) ;
		}

		public function url_base() {
			return self::$url_base[$this->type_prod] ;
		}

		public function url_api() {
			return self::$url_api[$this->type_prod] ;
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
				$token_ecriture = null ;

				$token_ecriture = $this->gimme_token($clientId,$secret) ;
				$this->debug($token_ecriture,'$token_ecriture') ;

				if ( ! $token_ecriture )
				{
					throw new \Exception('Impossible de récupérer le token d\'écriture pour '.$clientId) ;
				}

				if ( ! isset($token_ecriture->access_token) )
				{
					throw new \Exception('Le token d\'écriture n\'a pas pu être récupéré pour '.$clientId) ;	
				}
			}
			catch(\Exception $e) {
				$msg = sprintf( 'Curl failed with error #%d: %s', $e->getCode(), $e->getMessage() ) ;
				if ( $this->debug ) echo '<div class="alert alert-warning">'.$msg.'</div>' ;
				return Array('errorCode'=>$e->getCode(),'message'=>$e->getMessage()) ;
			}
			
			try {
				
				$ch = curl_init();
				
				curl_setopt($ch,CURLOPT_URL, $this->url_api().'api/v002/ecriture/');
				
				$header = Array() ;
				$header[] = "Authorization: Bearer ".$token_ecriture->access_token ;
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
				throw new \Exception('erreurs_enregistrement <br />'.print_r($ko,true)) ;
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
				$token_ecriture = null ;

				$token_ecriture = $this->gimme_token() ;
				$this->debug($token_ecriture,'$token_ecriture') ;

				if ( ! $token_ecriture )
				{
					throw new \Exception('Impossible de récupérer le token d\'écriture') ;
				}

				if ( ! isset($token_ecriture->access_token) )
				{
					throw new \Exception('Le token d\'écriture n\'a pas pu être récupéré pour') ;	
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
				$header[] = "Authorization: Bearer ".$token_ecriture->access_token ;
				curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
				curl_setopt($ch,CURLOPT_POSTFIELDS, $POSTFIELDS);
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

		function gimme_token($clientId=null,$secret=null)
		{
			$clientId = ( $clientId != null ) ? $clientId : $this->projet_ecriture_clientId ;
			$secret = ( $secret != null ) ? $secret : $this->projet_ecriture_secret ;

			$ch = curl_init() ;
			// http://stackoverflow.com/questions/15729167/paypal-api-with-php-and-curl
			curl_setopt($ch,CURLOPT_URL, $this->url_api().'oauth/token');
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
			curl_setopt($ch, CURLOPT_USERPWD, $clientId.":".$secret);
			curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
			
			try {
				$token = curl_exec($ch);

				if ( $token === false ) throw new \Exception(curl_error($ch), curl_errno($ch));
				else return json_decode($token) ;
			} catch(\Exception $e) {
				trigger_error(sprintf('Curl failed with error #%d: %s', $e->getCode(), $e->getMessage()), E_USER_ERROR);
				return false ;
			}
			
			return false ;
		}

		public function debug($var,$titre=null)
		{
			if ( ! $this->debug ) return ;
			echo '<p style="font-size:16px;font-weight:bold ;">[debug] '.(($titre!==null)?$titre:'').' / '.gettype($var).'</p>' ;
			echo '<textarea style="color:white;background:black;font-family:monospace;font-size:0.8em;width:100%;height:50px;">' ;
				if ( is_array($var) || is_object($var) || gettype($var) == 'boolean' ) echo var_dump($var) ;
				elseif ( $this->isJson($var) ) echo json_encode($var,JSON_PRETTY_PRINT) ;
				else echo $var ;
			echo '</textarea>' ;
		}

		// https://stackoverflow.com/questions/6041741/fastest-way-to-check-if-a-string-is-json-in-php
		private function isJson($string) {
			json_decode($string);
			return (json_last_error() == JSON_ERROR_NONE);
		}

		public function alerte($sujet,$msg,$mailto=null)
		{
			if ( ! filter_var($this->_config['mail_admin'], FILTER_VALIDATE_EMAIL) ) return false ;

			$from = ( isset($this->_config['mail_expediteur']) && filter_var($this->_config['mail_expediteur'], FILTER_VALIDATE_EMAIL) ) ? $this->_config['mail_expediteur'] : $this->_config['mail_admin'] ;
			$to = $this->_config['mail_admin'] ;

			if ( isset($mailto) && $mailto != null && filter_var($mailto, FILTER_VALIDATE_EMAIL) )
				$to = $mailto ;

			$endline = "\n" ;
			$h1 = strip_tags(get_class($this).' - '.$sujet) ;
			$sujet = $h1 ;
			
			if ( is_array($msg) )
			{
				$new_msg = null ;
				if ( isset($msg['message']) )
				{
					$new_msg .= $msg['message'] ;
					unset($msg['message']) ;
				}
				unset($msg['x']) ; unset($msg['y']) ;
				$tble = '<table style="clear:both; background:#FFF ; font-size:11px ; margin-bottom:20px ;" border="1" cellspacing="0" cellpadding="6">' ;
				foreach ( $msg as $key => $value )
				{
					$tble .= '<tr>' ;
						$tble .= '<th><strong>'.ucfirst($key).'</strong></th>' ;
						$tble .= '<td>' ;
							if ( ! is_array($value) ) $tble .= stripslashes(nl2br($value)) ;
							else
							{
								$tble .= '<pre>'.print_r($value,true).'</pre>' ;
							}
						$tble .= '</td>' ;
					$tble .= '</tr>' ;
				}
				$tble .= '</table>' ;
				$new_msg .= $tble ;
				$msg = $new_msg ;
			}

			$message_html = '<html style="text-align : center; margin : 0; padding:0 ; font-family:Verdana ;font-size:10px ;">'.$endline  ;
				$message_html .= '<div style="text-align:left ;">'.$endline ;
					$message_html .= '<div>'.$msg.'</div>'.$endline ;
				$message_html .= '</div>'.$endline ;
			$message_html .= '</html>'.$endline ;
			
			$message_texte = strip_tags(nl2br($message_html)) ;
			
			$boundary = md5(time()) ;
			
			$entete = Array() ;
			$entete['From'] = $from . '<'.$from.'>' ;
			$entete['Bcc'] = $this->_config['mail_admin'] ;
			$entete['Date'] = @date("D, j M Y G:i:s O") ;
			$entete['X-Mailer'] = 'PHP'.phpversion() ;
			$entete['MIME-Version'] = '1.0' ;
			$entete['Content-Type'] = 'multipart/alternative; boundary="'.$boundary.'"' ;
			
			$message = $endline ;
			$message .= $endline."--".$boundary.$endline ;
			$message .= "Content-Type: text/plain; charset=\"utf-8\"".$endline ;
			$message .= "Content-Transfer-Encoding: 8bit".$endline ;
			$message .= $endline.strip_tags(nl2br($message_html)) ;
			$message .= $endline.$endline."--".$boundary.$endline ;
			$message .= "Content-Type: text/html; charset=\"utf-8\"".$endline ;
			$message .= "Content-Transfer-Encoding: 8bit;".$endline ;
			$message .= $endline.$message_html ;
			$message .= $endline.$endline."--".$boundary."--";
			
			$header = null ;
			foreach ( $entete as $key => $value )
			{
				$header .= $key . ' : ' . $value . $endline ;
			}

			if ( ! preg_match("#\r#i",$to) && ! preg_match("#\n\r#i",$to) && ! preg_match("#\r#i",$from) && ! preg_match("#\n\r#i",$from) )
			{
				$ret = @mail($to,$sujet,$message,$header) ;
				if ( ! $ret )
					echo 'Erreur : '.print_r(error_get_last(),true) ;
			}
			else
				$ret = false ;
			
			return $ret ;
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
