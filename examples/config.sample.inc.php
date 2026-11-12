<?php

$configApidaeSso['ssoClientId'] = '';
$configApidaeSso['ssoSecret'] = '';

$configApidaeSsoPreprod['ssoClientId'] = '';
$configApidaeSsoPreprod['ssoSecret'] = '';
$configApidaeSsoPreprod['type_prod'] = 'preprod';
// URL de retour SSO fixe (ne jamais la déduire de HTTP_HOST / REQUEST_URI)
$configApidaeSsoPreprod['defaultSsoRedirectUrl'] = 'https://example.org/examples/autorisation_tokenSSO.php?type=preprod';
