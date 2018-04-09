<?php
require_once __DIR__ . '/../vendor/autoload.php';

$arrCredentials = array(
   'username' => 'xxx',
   'password' => 'xxx'
);

try {
   $objGarminConnect = new \dawguk\GarminConnect($arrCredentials);

   $objResults = $objGarminConnect->getActivityList(0, 1);
   print_r($objResults);

} catch (Exception $objException) {
   echo "Oops: " . $objException;
}
