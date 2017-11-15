<?php

	include(realpath(dirname(__FILE__)).'/../vendor/autoload.php') ;
	include(realpath(dirname(__FILE__)).'/../config.inc.php') ;

	$sujet = __FILE__ ;
	
	$to = Array(
		'p.granger@allier-tourisme.net',
		'apidaeevent@allier-tourisme.net',
		'apidae@allier-tourisme.net'
	) ;

	$admins = Array(
		'p.granger@allier-tourisme.net',
		'apidaeevent@allier-tourisme.net',
		'apidae@allier-tourisme.net'
	) ;

	$message = Array(
		'titre' => 'Mon titre',
		'adresse1' => 'Adresse 1',
		'from' => implode(', ',$admins),
		'to' => implode(', ',$to)
	) ;


	if (php_sapi_name() !== "cli") echo '<pre>' ;

	foreach ( $admins as $admin )
	{
		$ae_cfg = Array(
			'projet_ecriture_clientId' => $_config['projet_ecriture_clientId'],
			'projet_ecriture_secret' => $_config['projet_ecriture_secret'],
			'mail_admin' => $admins,
			'mail_expediteur' => $admin,
			'debug' => true
		) ;

		$ae = new \PierreGranger\ApidaeEcriture($ae_cfg) ;
		
		try {
			echo 'Envoi de '.$admin.' à '.implode(',',$to).' : ' ;
			$sujet = basename(__FILE__).' '.$admin.' => '.implode(',',$to) ;
			$ret = $ae->alerte($sujet,$message,$to) ;
			var_dump($ret) ;
			echo "\n" ;
		}
		catch ( Exception $e ) {
			print_r($e) ;
		}

	}