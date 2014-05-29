<<<<<<< HEAD
<<<<<<< HEAD
# Garmin Connect PHP Client
=======
# Strava v3 API Client

This is a single-file-library that you can use to interrogate the Strava v3 API. It handles authentication as well as whatever API calls you want to me (as long as they are valid)

## Usage

First of you need to construct a configuration array. The array should contain at least the following information:

* CLIENT_ID
* CLIENT_SECRET
* REDIRECT_URI
* ACCESS SCOPE

CLIENT_ID and CLIENT_SECRET should be taken from the [My API Application](https://www.strava.com/settings/api) section of the site. Valid ACCESS_SCOPE values can be found in the [Strava API documentation](http://strava.github.io/api/v3/oauth/)

Optionally, you can supply the following addition configuration options:

* CACHE_DIRECTORY
* ACCESS_TOKEN

If CACHE_DIRECTORY isn't supplied, the library falls back to writing to /tmp

If ACCESS_TOKEN is supplied, we bypass authorization and token exchange - assuming the ACCESS_TOKEN is correct.

## Examples

To configure the client, you would define your parameters as follows:

```php
<?php
$arrConfig = array(
   'CLIENT_ID' => 1354,
   'CLIENT_SECRET' => 'here is my client secret',
   'REDIRECT_URI' => 'http://localhost/example.php',
   'CACHE_DIRECTORY' => '/path/to/cache/dir/',
   'ACCESS_SCOPE' => 'write'
);
```

The following example GETs information about the authenticated athlete:

```php
<?php
$objStrava = new \Roflcopter\Strava($arrConfig);
print_r($objStrava->get('athlete', array()));
```

The following example PUTs (updates) the weight information for the current athlete:

```php
<?php
$objStrava = new \Roflcopter\Strava($arrConfig);
print_r($objStrava->put('athlete', array('weight' => 62.8)));
```

## Notes

Currently the library will only store a single access token, so isn't ready for multi-user authentication. This is expected to change in the future, with token storage abstracted out.

Now available on packagist.org ;D

## References

[http://strava.github.io/api/v3](http://strava.github.io/api/v3) is a good place to start.
>>>>>>> 09d8383ef35d34b295a950b43bec75d3891eb6a6
=======
php-garmin-connect
==================

A PHP adapter for interrogating the Garmin Connect "API"
>>>>>>> 4baef86989c5dff3fc2e290230885a72a6142535
