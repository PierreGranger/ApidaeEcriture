<?php

	include(realpath(dirname(__FILE__)).'/../vendor/autoload.php') ;
	include(realpath(dirname(__FILE__)).'/../config.inc.php') ;

	$ApidaeEcriture = new \PierreGranger\ApidaeEcriture(array_merge(
		$configApidaeEcriture,
		Array('debug' => true)
	)) ;
	
	try {
		$token = $ApidaeEcriture->gimme_token(null,null,true) ;
	}
	catch ( Exception $e ) {
		$ApidaeEcriture->showException($e) ;
	}

	var_dump($token) ;