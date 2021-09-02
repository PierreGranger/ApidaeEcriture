<?php

include(realpath(dirname(__FILE__)) . '/../vendor/autoload.php');
include(realpath(dirname(__FILE__)) . '/../config.inc.php');

$ae = new \PierreGranger\ApidaeEcriture($configApidaeEcriture);

$offres = [999999999, 5023027, 5163353, 4683815];
$criteres = [18180, 16951, 999999];

/**
 * Ajouts
 */
try {
    $res = $ae->critereInterne('PUT', $offres, $criteres);
    var_dump($res);
    if ($res === false) print_r($ae->lastResult());
} catch (Exception $e) {
    print_r($e);
}

echo PHP_EOL;

/**
 * Suppressions
 */
try {
    $res = $ae->critereInterne('DELETE', $offres, $criteres);
    var_dump($res);
    if ($res === false) print_r($ae->lastResult());
} catch (Exception $e) {
    print_r($e);
}
