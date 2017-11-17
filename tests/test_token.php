<?php

	include(realpath(dirname(__FILE__)).'/../vendor/autoload.php') ;
	include(realpath(dirname(__FILE__)).'/../config.inc.php') ;

	$ae_cfg = Array(
		'projet_ecriture_clientId' => $_config['projet_ecriture_clientId'],
		'projet_ecriture_secret' => $_config['projet_ecriture_secret'],
		'mail_admin' => $admins,
		'mail_expediteur' => $admin,
		'debug' => true
	) ;

	$ae = new \PierreGranger\ApidaeEcriture($ae_cfg) ;
	
	try {
		$token = $ae->gimme_token(null,null,true) ;
		var_dump($token) ;
	}
	catch ( Exception $e ) {
		print_r($e) ;
		var_dump($token) ;
	}
