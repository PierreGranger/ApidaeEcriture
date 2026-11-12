<?php

include(realpath(dirname(__FILE__)) . '/../vendor/autoload.php');
include(realpath(dirname(__FILE__)) . '/../config.inc.php');

include(realpath(dirname(__FILE__)) . '/./vendor/autoload.php'); // Vendor pour examples (ApidaeSso)
include(realpath(dirname(__FILE__)) . '/./config.inc.php'); // Config pour examples (ApidaeSso)

use PierreGranger\ApidaeSso;
use PierreGranger\ApidaeEcriture;

session_start();

$sep = "\n";
echo '<pre>' . $sep;

/**
 * Tests sur prod
 */
if (isset($configApidaeSso) && $configApidaeSso['ssoSecret'] != '') {

    $ApidaeSso = new ApidaeSso($configApidaeSso, $_SESSION['ApidaeSso']);
    if (isset($_GET['logout'])) $ApidaeSso->logout();

    if (isset($_GET['code']) && (!isset($_GET['type']) || $_GET['type'] == 'prod') && !$ApidaeSso->connected()) {
        try {
            $ApidaeSso->getSsoToken($_GET['code']);
        } catch (Exception $e) {
            echo htmlspecialchars($e->getMessage());
        }
    }

    if (!$ApidaeSso->connected()) echo '<a href="' . htmlspecialchars($ApidaeSso->getSsoUrl(), ENT_QUOTES) . '">SSO Prod</a>';
    else {
        $sep = "\n";

        $apidaeEcriture = new ApidaeEcriture(array_merge(
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

        echo $sep . '******** PROD ********' . $sep;

        $profile = $ApidaeSso->getUserProfile(true);

        foreach ($tests as $id_offre => $desc) {
            echo $sep . $id_offre . ' => ' . $desc . $sep;
            echo 'Autorisation pour ' . htmlspecialchars($profile['firstName'] . ' ' . $profile['lastName']) . ' ? ' . ($apidaeEcriture->autorisation($id_offre, $_SESSION['ApidaeSso']['sso']['access_token']) ? 'Oui' : 'Non') . $sep;
            echo 'Détail : ' . $apidaeEcriture->lastAutorisationDetail() . $sep;
        }
    }
}





/***Tests preprod */

if (isset($configApidaeSsoPreprod) && $configApidaeSsoPreprod['ssoSecret'] != '' && isset($configApidaeEcriturePreprod)) {

    echo $sep . '<hr />Preprod' . $sep;
    // Ne pas construire l'URL de retour SSO depuis HTTP_HOST / REQUEST_URI (injection d'en-tête Host, XSS) :
    // elle doit être fixe et déclarée dans la configuration.
    $ssoRedirectUrl = $configApidaeSsoPreprod['defaultSsoRedirectUrl'] ?? null;
    if ($ssoRedirectUrl === null) {
        exit('defaultSsoRedirectUrl manquant dans $configApidaeSsoPreprod');
    }

    $ApidaeSsoPreprod = new ApidaeSso(
        array_merge(
            $configApidaeSsoPreprod,
            ['defaultSsoRedirectUrl' => $ssoRedirectUrl]
        ),
        $_SESSION['ApidaeSsoPreprod']
    );
    if (isset($_GET['logout'])) $ApidaeSsoPreprod->logout();

    if (isset($_GET['code']) && isset($_GET['type']) && $_GET['type'] == 'preprod' && !$ApidaeSsoPreprod->connected()) {
        try {
            $ApidaeSsoPreprod->getSsoToken($_GET['code']);
        } catch (Exception $e) {
            echo htmlspecialchars($e->getMessage());
        }
    }

    if (!$ApidaeSsoPreprod->connected()) echo '<a href="' . htmlspecialchars($ApidaeSsoPreprod->getSsoUrl($ssoRedirectUrl), ENT_QUOTES) . '">SSO PreProd</a>';
    else {

        $profile = $ApidaeSsoPreprod->getUserProfile(true);

        $apidaeEcriture = new ApidaeEcriture(array_merge(
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
            echo 'Autorisation pour ' . htmlspecialchars($profile['firstName'] . ' ' . $profile['lastName']) . ' ? ' . ($apidaeEcriture->autorisation($id_offre, $_SESSION['ApidaeSsoPreprod']['sso']['access_token']) ? 'Oui' : 'Non') . $sep;
            echo 'Détail : ' . $apidaeEcriture->lastAutorisationDetail() . $sep;
        }
    }
}
