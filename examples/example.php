<?php

use dawguk\GarminConnect;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . "/credentials.php";


try {
    $objGarminConnect = new GarminConnect($arrCredentials, __DIR__ . "/../log/garminconnect.log");
    $objGarminConnect->login();
    $objResults = $objGarminConnect->getActivityList(0, 5, 'cycling');
    print_r($objResults);

} catch (Exception $objException) {
    echo "Oops: " . $objException;
}
