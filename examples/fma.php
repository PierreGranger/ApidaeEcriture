<?php

use PierreGranger\ApidaeException;

include(realpath(dirname(__FILE__)) . '/../vendor/autoload.php');
include(realpath(dirname(__FILE__)) . '/../config.inc.php');

$apidaeEcriture = new \PierreGranger\ApidaeEcriture(array_merge(
	$configApidaeEcriture,
	array(
		'debug' => true,
		'type_prod' => 'prod',
		'skipValidation' => true,
	)
));

$root = array();
$fieldlist = array();

$root['type'] = 'FETE_ET_MANIFESTATION';

$fieldlist[] = 'nom';
$root['nom']['libelleFr'] = 'FMA TEST ApidaeEcriture ' . date('d/m/Y');

$fieldlist[] = 'localisation.adresse.adresse1';
$root['localisation']['adresse']['adresse1'] = 'Adresse 1';
$fieldlist[] = 'localisation.adresse.adresse2';
$root['localisation']['adresse']['adresse2'] = 'Adresse 2';
$fieldlist[] = 'localisation.adresse.adresse3';
$root['localisation']['adresse']['adresse3'] = 'Adresse 3';
$fieldlist[] = 'localisation.adresse.codePostal';
$root['localisation']['adresse']['codePostal'] = '03400';
$fieldlist[] = 'localisation.adresse.commune';
$root['localisation']['adresse']['commune']['id'] = 1555; // Yzeure

$root['informationsFeteEtManifestation']['portee']['elementReferenceType'] = 'FeteEtManifestationPortee';
$fieldlist[] = 'informationsFeteEtManifestation.portee';
$root['informationsFeteEtManifestation']['portee']['id'] = 2354; // Habitants

$contacts = array();

$contacts[] = array(
	'referent' => true,
	'nom' => 'Nom contact 1',
	'prenom' => 'Prénom contact 1',
	'moyensCommunication' => array(
		array('type' => array('id' => 204, 'elementReferenceType' => 'MoyenCommunicationType'), 'coordonnees' => array('fr' => 'contact1@mail.fr')),
		array('type' => array('id' => 201, 'elementReferenceType' => 'MoyenCommunicationType'), 'coordonnees' => array('fr' => '01 02 03 04 05'))
	)
);

$contacts[] = array(
	'nom' => 'Nom contact 2',
	'prenom' => 'Prénom contact 2',
	'moyensCommunication' => array(
		array('type' => array('id' => 204, 'elementReferenceType' => 'MoyenCommunicationType'), 'coordonnees' => array('fr' => 'contact2@mail.fr')),
		array('type' => array('id' => 201, 'elementReferenceType' => 'MoyenCommunicationType'), 'coordonnees' => array('fr' => '06 07 08 09 10'))
	)
);

if (sizeof($contacts) > 0) {
	$fieldlist[] = 'contacts';
	$root['contacts'] = $contacts;
}

$periodesOuvertures = array();

$periodesOuvertures[] = array(
	'dateDebut' => date('Y-m-d'),
	'dateFin' => date('Y-m-d', strtotime('+1 day')),
	'horaireOuverture' => "11:00:00",
	'horaireFermeture' => "12:00:00",
	'tousLesAns' => false,
	'type' => 'OUVERTURE_SAUF'
);

$periodesOuvertures[] = array(
	'dateDebut' => date('Y-m-d', strtotime('+1 week')),
	'dateFin' => date('Y-m-d', strtotime('+1 month')),
	'horaireOuverture' => "16:00:00",
	'horaireFermeture' => "17:00:00",
	'tousLesAns' => false,
	'type' => 'OUVERTURE_SAUF'
);


if (sizeof($periodesOuvertures) > 0) {
	$fieldlist[] = 'ouverture.periodesOuvertures';
	$root['ouverture']['periodesOuvertures'] = $periodesOuvertures;
}



$medias = array();
$root['illustrations'] = array();

/*
	*	Exemple volontairement simpliste donné uniquement pour tester l'enregistrement.
	*	Il va de soi qu'avant d'envoyer un fichier vous devez vérifier qu'il s'agit bien d'une image.
	*/
$image = realpath(dirname(__FILE__) . '/logo-Apidae-760x350.jpg');

$medias['multimedia.illustration-1'] = $apidaeEcriture->getCurlValue($image, mime_content_type($image), basename($image));
$illustration = array();
$illustration['link'] = false;
$illustration['type'] = 'IMAGE';
$illustration['nom']['libelleFr'] = 'Légende image 1';
$illustration['copyright']['libelleFr'] = 'Copyright image 1';
$illustration['traductionFichiers'][0]['locale'] = 'fr';
$illustration['traductionFichiers'][0]['url'] = 'MULTIMEDIA#illustration-1';
$root['illustrations'][] = $illustration;

$fieldlist[] = 'illustrations';

$enregistrer = array(
	'fieldlist' => $fieldlist,
	'root' => $root,
	'medias' => $medias
);

$ko = array();

try {
	$ko = $apidaeEcriture->ajouter($enregistrer);
} catch (ApidaeException $e) {
	print_r($e->getDetails());
	$apidaeEcriture->showException($e);
}

$apidaeEcriture->alerte(__FILE__, $ko);

print_r($ko);
