<?php

use dawguk\GarminConnect;

require_once __DIR__ . '/../vendor/autoload.php';

$arrCredentials = array(
    'username' => 'xxx',
    'password' => 'xxx'
);

try {
    $objGarminConnect = new GarminConnect($arrCredentials);

    $objResults = $objGarminConnect->getActivityList(0, 5, 'cycling');
    print_r($objResults);

} catch (Exception $objException) {
    echo "Oops: " . $objException;
}
