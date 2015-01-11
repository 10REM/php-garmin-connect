PHP Garmin Connect
==================

A PHP adapter for interrogating the Garmin Connect "API"

Preamble
========

Garmin doesn't really have a proper API for their Connect tool. Well, they sort of do, but it's half-baked; they appear to
have either abandoned it or let it go stale, the documentation is very thin on the ground and there appears to be
no "proper" way of authenticating the user.

So thanks to Collin @tapiriik and his wonderful public python repo (https://github.com/cpfair/tapiriik), this project
was born for those of us that prefer elephants to snakes ;)

The code is pretty well documented, and it has to be because some things we have to do is pretty gnarly. Once authentication
is done though, it's pretty much good old RESTFUL API stuff. Oh, but we're using the CURL cookie handling to maintain
session state. Ugh.

Example
=======

We simply connect using our Garmin Connect credentials, with an additional
identifier. The identifier is used when writing our session to disk, and it means we shouldn't need to authenticate
over and over again (it works just as any cookie should work in a web browser).

```php
<?php
$arrCredentials = array(
'username' => 'xxx',
'password' => 'xxx',
'identifier' => '<any identifier>'
);

try {
   $objGarminConnect = new \dawguk\GarminConnect($arrCredentials);

   $objResults = $objGarminConnect->getActivityList(0, 1);
   foreach($objResults->results->activities as $objActivity) {
      print_r($objActivity->activity);
   }

} catch (Exception $objException) {
   echo "Oops: " . $objException->getMessage();
}
```
