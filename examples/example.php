<?php
require_once '../vendor/autoload.php';

$arrCredentials = array(
   'username' => 'xxx',
   'password' => 'xxx'
);

try {
   $objGarminConnect = new \dawguk\GarminConnect($arrCredentials);

   $objResults = $objGarminConnect->getActivityList();
   print_r($objResults);

} catch (Exception $objException) {
   echo "Oops: " . $objException->getMessage();
}
