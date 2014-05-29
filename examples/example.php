<?php

$arrConfig = array(
   'CLIENT_ID' => 1354,
   'CLIENT_SECRET' => 'here is my client secret',
   'REDIRECT_URI' => 'http://localhost/example.php',
   'CACHE_DIRECTORY' => '/path/to/cache/dir/'
);

$objStrava = new \dawguk\Strava($arrConfig);
print_r($objStrava->get('athlete', array()));
