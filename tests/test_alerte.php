<?php

	include(realpath(dirname(__FILE__)).'/../vendor/autoload.php') ;
	include(realpath(dirname(__FILE__)).'/../config.inc.php') ;

	$sujet = __FILE__ ;
	$message = Array(
		'titre' => 'Mon titre',
		'adresse1' => 'Adresse 1'
	) ;

	$to = Array(
		'p.granger@allier-tourisme.net'
	) ;

	$ae = new \PierreGranger\ApidaeEcriture(Array(
		'projet_ecriture_clientId' => $_config['projet_ecriture_clientId'],
		'projet_ecriture_secret' => $_config['projet_ecriture_secret'],
		'mail_admin' => $_config['mail_admin'],
		'debug' => true
	)) ;

	try {
		if ( ! is_array($to) ) $to = Array($to) ;
		echo '<pre>' ;
		foreach ( $to as $t )
		{
			echo 'Envoi à '.$t.' : ' ;
			var_dump($ae->alerte(__FILE__,$message,to)) ;
			echo "\n" ;
		}
		echo '</pre>' ;
	}
	catch ( Exception $e ) {
		echo '<pre>' ;
		print_r($e) ;
		echo '</pre>' ;
	}