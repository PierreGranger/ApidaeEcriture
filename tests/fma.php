<?php

	include(realpath(dirname(__FILE__)).'/../vendor/autoload.php') ;
	include(realpath(dirname(__FILE__)).'/../config.inc.php') ;

	$ApidaeEcriture = new \PierreGranger\ApidaeEcriture(array_merge(
		$configApidaeEcriture,
		Array(
			'type_prod' => 'preprod',
			'skipValidation' => true,
		)
	)) ;

	$root = Array() ;
	$fieldlist = Array() ;
	
	$root['type'] = 'FETE_ET_MANIFESTATION' ;

	$fieldlist[] = 'nom' ;
	$root['nom']['libelleFr'] = 'FMA TEST ApidaeEcriture' ;

	$fieldlist[] = 'localisation.adresse.adresse1' ;
	$root['localisation']['adresse']['adresse1'] = 'Adresse 1' ;
	$fieldlist[] = 'localisation.adresse.adresse2' ;
	$root['localisation']['adresse']['adresse2'] = 'Adresse 2' ;
	$fieldlist[] = 'localisation.adresse.adresse3' ;
	$root['localisation']['adresse']['adresse3'] = 'Adresse 3' ;
	$fieldlist[] = 'localisation.adresse.codePostal' ;
	$root['localisation']['adresse']['codePostal'] = '03400' ;
	$fieldlist[] = 'localisation.adresse.commune' ;
	$root['localisation']['adresse']['commune']['id'] = 1555 ; // Yzeure

	$root['informationsFeteEtManifestation']['portee']['elementReferenceType'] = 'FeteEtManifestationPortee' ;
	$fieldlist[] = 'informationsFeteEtManifestation.portee' ;
	$root['informationsFeteEtManifestation']['portee']['id'] = 2354 ; // Habitants

	$contacts = Array() ;

	$contacts[] = Array(
		'referent' => true,
		'nom' => 'Nom contact 1',
		'prenom' => 'Prénom contact 1',
		'moyensCommunication' => Array(
			Array('type' =>Array('id'=>204,'elementReferenceType' => 'MoyenCommunicationType'), 'coordonnees'=> Array('fr'=>'contact1@mail.fr') ),
			Array('type' =>Array('id'=>201,'elementReferenceType' => 'MoyenCommunicationType'), 'coordonnees'=> Array('fr'=>'01 02 03 04 05') )
		)
	) ;

	$contacts[] = Array(
		'nom' => 'Nom contact 2',
		'prenom' => 'Prénom contact 2',
		'moyensCommunication' => Array(
			Array('type' =>Array('id'=>204,'elementReferenceType' => 'MoyenCommunicationType'), 'coordonnees'=> Array('fr'=>'contact2@mail.fr') ),
			Array('type' =>Array('id'=>201,'elementReferenceType' => 'MoyenCommunicationType'), 'coordonnees'=> Array('fr'=>'06 07 08 09 10') )
		)
	) ;

	if ( sizeof($contacts) > 0 )
	{
		$fieldlist[] = 'contacts' ;
		$root['contacts'] = $contacts ;
	}

	$periodesOuvertures = Array() ;

	$periodesOuvertures[] = Array(
		'dateDebut' => '2020-01-01',
		'dateFin' => '2020-01-01',
		'horaireOuverture' => "11:00:00",
		'horaireFermeture' => "12:00:00",
		'tousLesAns' => false,
		'type' => 'OUVERTURE_SAUF'
	) ;

	$periodesOuvertures[] = Array(
		'dateDebut' => '2020-02-02',
		'dateFin' => '2020-02-02',
		'horaireOuverture' => "16:00:00",
		'horaireFermeture' => "17:00:00",
		'tousLesAns' => false,
		'type' => 'OUVERTURE_SAUF'
	) ;


	if ( sizeof ($periodesOuvertures) > 0  )
	{
		$fieldlist[] = 'ouverture.periodesOuvertures' ;
		$root['ouverture']['periodesOuvertures'] = $periodesOuvertures ;
	}

	

	$medias = Array() ;
	$root['illustrations'] = Array() ;

	/*
	*	Exemple volontairement simpliste donné uniquement pour tester l'enregistrement.
	*	Il va de soi qu'avant d'envoyer un fichier vous devez vérifier qu'il s'agit bien d'une image.
	*/
	$image = realpath(dirname(__FILE__).'/logo-Apidae-760x350.jpg') ;

	$medias['multimedia.illustration-1'] = $ae->getCurlValue($image,mime_content_type($image),basename($image)) ;
	$illustration = Array() ;
	$illustration['link'] = false ;
	$illustration['type'] = 'IMAGE' ;
	$illustration['nom']['libelleFr'] = 'Légende image 1' ;
	$illustration['copyright']['libelleFr'] = 'Copyright image 1' ;
	$illustration['traductionFichiers'][0]['locale'] = 'fr' ;
	$illustration['traductionFichiers'][0]['url'] = 'MULTIMEDIA#illustration-1' ;
	$root['illustrations'][] = $illustration ;

	$fieldlist[] = 'illustrations' ;

	$enregistrer = Array(
		'fieldlist' => $fieldlist,
		'root' => $root,
		'medias' => $medias
	) ;

	try {
		$ko = $ae->ajouter($enregistrer) ;
	}
	catch ( Exception $e ) {
		echo '<pre>' ;
		print_r($e) ;
		echo '</pre>' ;
	}
	
	$ae->alerte(__FILE__,$ko) ;

	print_r($ko) ;