<?php

include(realpath(dirname(__FILE__)) . '/../vendor/autoload.php');
include(realpath(dirname(__FILE__)) . '/../config.inc.php');

$sep = "\n";

$apidaeEcriture = new \PierreGranger\ApidaeEcriture(array_merge(
    $configApidaeEcriture,
    array(
        'debug' => false,
        'skipValidation' => true,
    )
));

$tests = [
    834049 => '',
    22646 => '',
    205445 => '',
    5411881 => '',
    472408 => '',
    181799 => '',
    1 => 'N\'existe pas', // N'existe pas
    5163353 => 'Offre masquée appartenant au propriétaire du projet d\'écriture', // Masqué
];

foreach ($tests as $id_offre => $desc) {
    echo $sep . $id_offre . ' => ' . $desc . $sep;
    echo 'Autorisation ? ' . ($apidaeEcriture->autorisation($id_offre) ? 'Oui' : 'Non') . $sep;
    echo 'Détail : ' . $apidaeEcriture->lastAutorisationDetail() . $sep;
}


/**
 * Si besoin, tests sur preprod
 */

if (!isset($configApidaeEcriturePreprod)) return;

$apidaeEcriture = new \PierreGranger\ApidaeEcriture(array_merge(
    $configApidaeEcriturePreprod,
    array(
        'debug' => false,
        'skipValidation' => true,
    )
));
$tests = [
    4659645 => 'EQU Villeperdue, masqué, propriété Apidae Tourisme. Réponse attendue : OUI',
    4730670 => 'HLO Chambre d\'hôtes propriété Clévacances Ardèche, Apidae Tourisme est collaborateur. Réponse attendue : OUI',
    5181333 => 'RES sur Laps (63), propriété "OT Fictif Apidae" : OT Fictif est abonné au projet d\'écriture. Réponse attendue : OUI',
    4709611 => 'COS : appartient à Allier Tourisme (non abonné au projet). Réponse attendue : NON',
    4912760 => 'EQU : appartient à Allier Tourisme (non abonné au projet), OT Fictif Apidae (abonné au projet) est collaborateur. Réponse attendue : OUI',
    1 => 'N\'existe pas. Réponse attendue : NON',
];

echo $sep . '******** PREPROD ********' . $sep;

foreach ($tests as $id_offre => $desc) {
    echo $sep . $id_offre . ' => ' . $desc . $sep;
    echo 'Autorisation ? ' . ($apidaeEcriture->autorisation($id_offre) ? 'Oui' : 'Non') . $sep;
    echo 'Détail : ' . $apidaeEcriture->lastAutorisationDetail() . $sep;
}
