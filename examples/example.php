<?php
require_once '../vendor/autoload.php';

$arrCredentials = array(
'username' => 'xxx',
'password' => 'xxx',
'identifier' => '1'
);

try {
   $objGarminConnect = new \dawguk\GarminConnect($arrCredentials);

   $objResults = $objGarminConnect->getExtendedActivityDetails(593520370);
   print_r($objResults);

} catch (Exception $objException) {
   echo "Oops: " . $objException->getMessage();
}
