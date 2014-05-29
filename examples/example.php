<?php
require_once '../vendors/autload.php';

$arrCredentials = array(
'username' => 'xxx',
'password' => 'xxx'
);

$objGarminConnect = new GarminConnect($arrCredentials);

$objResults = $objGarminConnect->getActivityList(0, 1);
foreach($objResults->results->activities as $objActivity) {
$intActivityId = (int)$objActivity->activity->activityId;
$strTCXUrl = "http://connect.garmin.com/proxy/activity-service-1.1/tcx/activity/" . $intActivityId . "?full=true";
file_put_contents("/tmp/" . $intActivityId . ".tcx", fopen($strTCXUrl, "r"));
}