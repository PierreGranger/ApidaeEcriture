<?php

	namespace PierreGranger ;
/**
*
* @author  Pierre Granger <pierre@pierre-granger.fr>
*
*/

	class ApidaeEcriture {

		protected static $url_api = Array(
			'preprod' => 'https://api.apidae-tourisme-recette.accelance.net/',
			'prod' => 'https://api.apidae-tourisme.com/'
		) ;

		protected static $url_base = Array(
			'preprod' => 'https://base.apidae-tourisme-recette.accelance.net/',
			'prod' => 'https://base.apidae-tourisme.com/'
		) ;

		protected $type_prod = 'prod' ;

		public $skipValidation = 'false' ;

		public $debug ;

		public $statuts_api_ecriture = Array('CREATION_VALIDATION_SKIPPED','CREATION_VALIDATION_ASKED','MODIFICATION_VALIDATION_SKIPPED','MODIFICATION_VALIDATION_ASKED','MODIFICATION_NO_DIFF','DEMANDE_SUPPRESSION_SENT','NO_ACTION') ;

		protected static $modes = Array('CREATION','MODIFICATION','DEMANDE_SUPPRESSION') ;

		protected $_config ;

		public $debugTime = false ;

		public $last_id = null ;

		protected $projet_ecriture_clientId ;
		protected $projet_ecriture_secret ;
		protected $projet_ecriture_projectId ;

		public function __construct($params=null) {
			
			if ( $this->debugTime ) $start = microtime(true) ;

			if ( ! is_array($params) ) throw new \Exception('$params is not an array') ;

			if ( isset($params['debug']) && $params['debug'] == true ) $this->debug = $params['debug'] ; else $this->debug = false ;

			if ( isset($params['type_prod']) && in_array($params['type_prod'],Array('prod','preprod')) ) $this->type_prod = $params['type_prod'] ;

			if ( isset($params['projet_ecriture_clientId']) ) $this->projet_ecriture_clientId = $params['projet_ecriture_clientId'] ; else throw new \Exception('missing projet_ecriture_clientId') ;
			if ( isset($params['projet_ecriture_secret']) ) $this->projet_ecriture_secret = $params['projet_ecriture_secret'] ; else throw new \Exception('missing projet_ecriture_secret') ;
			if ( isset($params['projet_ecriture_projectId']) ) $this->projet_ecriture_projectId = $params['projet_ecriture_projectId'] ;

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

		public function gimme_token($clientId=null,$secret=null,$debugToken=false)
		{
			$clientId = ( $clientId != null ) ? $clientId : $this->projet_ecriture_clientId ;
			$secret = ( $secret != null ) ? $secret : $this->projet_ecriture_secret ;

			$method = 'curl' ;

			if ( class_exists('\Sitra\ApiClient\Client') && $this->debug )
				$method = 'tractopelle' ;
			
			if ( $method == 'tractopelle' )
			{
				$client = new \Sitra\ApiClient\Client([
				    'ssoClientId'    => $clientId,
				    'ssoSecret'      => $secret
				]);

				$token = $client->getSsoTokenCredential() ;
				return $token['access_token'] ;
			}
			elseif ( $method == 'file_get_contents' )
			{
				// https://stackoverflow.com/a/2445332/2846837
				// https://stackoverflow.com/a/14253379/2846837

				$postdata = http_build_query(
					Array(
						'grant_type' => 'client_credentials'
					)
				) ;

				$opts = Array(
					'http' => Array(
						'method' => 'POST',
						'header' => 'Accept: application/json'."\r\n" .
									'Content-Type: application/x-www-form-urlencoded'."\r\n".
									'Authorization: Basic '.base64_encode($clientId.':'.$secret)."\r\n",
						'content' => $postdata
					)
				) ;

				$context = stream_context_create($opts) ;

				$retour = file_get_contents($this->url_api().'oauth/token',false,$context) ;
				if ( ! $retour )
				{
					if ( $this->debug )
					{
						$error = error_get_last() ;
						echo '<pre>'.print_r($error,true).'</pre>' ;
					}
					return false ;
				}

				$retour_json = json_encode($retour) ;
				if ( json_last_error() !== JSON_ERROR_NONE ) return false ;

				return $retour_json->access_token ;
			}
			elseif ( $method == 'curl' )
			{
				$ch = curl_init() ;
				// http://stackoverflow.com/questions/15729167/paypal-api-with-php-and-curl
				curl_setopt($ch, CURLOPT_URL, $this->url_api().'oauth/token');
				curl_setopt($ch, CURLOPT_HEADER, 1);
				curl_setopt($ch, CURLOPT_VERBOSE, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
				//curl_setopt($ch, CURLOPT_SSLVERSION, 6);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
				curl_setopt($ch, CURLOPT_USERPWD, $clientId.":".$secret);
				curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
				curl_setopt($ch, CURLOPT_TIMEOUT, 4);
				
				try {
					$response = curl_exec($ch);

					$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
					$header = substr($response, 0, $header_size);
					$body = substr($response, $header_size);
					
					$token_json = json_decode($body) ;

					if ( $debugToken )
					{
						echo '<pre>URL'."\n".$this->url_api().'oauth/token</pre>' ;
						echo '<pre>CURL_GETINFO'."\n".print_r(curl_getinfo($ch),true).'</pre>' ;
						echo '<pre>CURL_VERSION'."\n".print_r(curl_version(),true).'</pre>' ;
						echo '<pre>HEADER'."\n".print_r($header,true).'</pre>' ;
						echo '<pre>BODY'."\n".print_r($body,true).'</pre>' ;
						echo '<pre>token_json'."\n".print_r($token_json,true).'</pre>' ;
					}

					if ( curl_errno($ch) !== 0 ) throw new \Exception(__LINE__.curl_error($ch), curl_errno($ch));
					elseif ( json_last_error() !== JSON_ERROR_NONE ) throw new \Exception(__LINE__.'gimme_token : le retour de curl n\'est pas une chaîne json valide');
					else return $token_json->access_token ;
				} catch(\Exception $e) {
					if ( $this->debug )
					{
						echo '<pre>URL'."\n".$this->url_api().'oauth/token</pre>' ;
						echo '<pre>CURL_GETINFO'."\n".print_r(curl_getinfo($ch),true).'</pre>' ;
						echo '<pre>CURL_VERSION'."\n".print_r(curl_version(),true).'</pre>' ;
						echo '<pre>HEADER'."\n".print_r($header,true).'</pre>' ;
						echo '<pre>BODY'."\n".print_r($body,true).'</pre>' ;
						echo '<pre>CODE'."\n".print_r($e->getCode(),true).'</pre>' ;
						echo '<pre>MESSAGE'."\n".print_r($e->getMessage(),true).'</pre>' ;
					}
					return false ;
				}
			}
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
		protected function isJson($string) {
			json_decode($string);
			return (json_last_error() == JSON_ERROR_NONE);
		}

		public function alerte($sujet,$msg,$mailto=null,$options=null)
		{
			if ( is_array($this->_config['mail_admin']) )
			{
				foreach ( $this->_config['mail_admin'] as $mail_admin )
				{
					if ( ! filter_var($mail_admin, FILTER_VALIDATE_EMAIL) ) throw new \Exception(__LINE__.' mail admin incorrect : '.$mail_admin) ;
					if ( ! isset($first_mail_admin) ) $first_mail_admin = $mail_admin ;
				}
				$mails_admin = $this->_config['mail_admin'] ;
			}
			else
			{
				if ( ! filter_var($this->_config['mail_admin'], FILTER_VALIDATE_EMAIL) ) throw new \Exception(__LINE__.' mail admin incorrect : '.$this->_config['mail_admin']) ;
				$first_mail_admin = $this->_config['mail_admin'] ;
				$mails_admin = Array($this->_config['mail_admin']) ;
			}

			$from = ( isset($this->_config['mail_expediteur']) && filter_var($this->_config['mail_expediteur'], FILTER_VALIDATE_EMAIL) ) ? $this->_config['mail_expediteur'] : $first_mail_admin ;
			
			if ( is_array($mailto) )
			{
				foreach ( $mailto as $mt )
					if ( ! filter_var($mt, FILTER_VALIDATE_EMAIL) ) throw new \Exception(__LINE__.' mail to incorrect'.print_r($mt,true)) ;
			}
			elseif ( $mailto !== null )
			{
				if ( ! filter_var($mailto, FILTER_VALIDATE_EMAIL) ) throw new \Exception(__LINE__.' mail to incorrect'.print_r($mailto,true)) ;
				$mailto = Array($mailto) ;
			}
			else
				$mailto = $mails_admin ;

			$reflect = new \ReflectionClass($this) ;
			$className = $reflect->getShortName() ;

			$endline = "\n" ;
			$h1 = strip_tags($className.' - '.$sujet) ;
			$sujet = $h1 ;

			$method = 'mail' ;

			if ( class_exists('\PHPMailer\PHPMailer\PHPMailer') ) $method = 'phpmailer6' ;
			elseif ( class_exists('\PHPMailer') ) $method = 'phpmailer5' ;

			if ( $this->debug ) $sujet .= ' ['.$method.']' ;

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
			
			if ( $method == 'phpmailer5' )
			{
				$mail = new \PHPMailer();
				try {
				    $mail->setFrom($from) ;
				   	
				   	foreach ( $mailto as $t )
				    	$mail->addAddress($t) ;
				    
				    foreach ( $mails_admin as $mail_admin )
				    	$mail->AddBCC($mail_admin) ;

				    $mail->CharSet = 'UTF-8' ;
				    $mail->isHTML(true);
				    $mail->Subject = $sujet ;
				    $mail->Body    = $message_html ;
				    $mail->AltBody = $message_texte ;
					
				    return $mail->send() ;

				} catch (\Exception $e) {
				    throw new \Exception($e) ;
				}
			}
			elseif ( $method == 'phpmailer6' )
			{
				$mail = new \PHPMailer\PHPMailer\PHPMailer();
				try {
				    $mail->setFrom($from) ;
				   	
				   	foreach ( $mailto as $t )
				    	$mail->addAddress($t) ;
				    
				    foreach ( $mails_admin as $mail_admin )
				    	$mail->AddBCC($mail_admin) ;

				    $mail->CharSet = 'UTF-8' ;
				    $mail->isHTML(true);
				    $mail->Subject = $sujet ;
				    $mail->Body    = $message_html ;
				    $mail->AltBody = $message_texte ;
					
				    return $mail->send();

				} catch (\PHPMailer\PHPMailer\Exception $e) {
				    echo 'Message could not be sent.';
				    echo 'Mailer Error: ' . $mail->ErrorInfo;
				    throw new \Exception($e) ;
				}
			}
			else
			{
				$boundary = md5(time()) ;
				
				$entete = Array() ;
				$entete['From'] = $from ;
				$entete['Bcc'] = implode(',',$mails_admin) ;
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

				$ret = @mail(implode(',',$mailto),$sujet,$message,$header) ;
				if ( ! $ret )
					throw new \Exception('Erreur : '.print_r(error_get_last(),true)) ;
				
				return $ret ;
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
