<?php
namespace dawguk;

function oauthUrlencode($value) {
    return str_ireplace(
            ['+', '%7E'],
            [' ', '~'],
            rawurlencode((string) $value)
        );
}

class Auth1Sign
{
    private $consumerKey;
    private $consumerSecret;
    private $tokenSecret;
    private $method;

    public function __construct($consumerKey, $consumerSecret, $method='GET', $tokenSecret= NULL)
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->tokenSecret = $tokenSecret;
        $this->method = $method;
    }

    function oauthgetParameters($key, $nonce, $timeStamp)
    {
        return array(
        'oauth_consumer_key'     => $key,
        'oauth_nonce'            => (isset($nonce)? $nonce : md5(microtime() . random_bytes(16))),
        'oauth_signature_method'=> 'HMAC-SHA1',
        'oauth_timestamp'       => (isset($timeStamp)? $timeStamp : time()),
        'oauth_version'         => '1.0');
    }



    function prepareParameters($oauthParams, $queryParams)
    {
        // 4. Fusionner tous les paramètres (OAuth + query/body)
        $allParams = array_merge($oauthParams, $queryParams);

        // a) Trier par clé puis par valeur (lexicographique)
        uksort($allParams, 'strcmp');
        foreach ($allParams as $k => $v) {
            $allParams[$k] = (array)$v; // assure que chaque valeur est un tableau
        }
        ksort($allParams, SORT_STRING);

        // b) Construire la chaîne «paramètre=valeur» encodée
        $parameterParts = [];
        foreach ($allParams as $key => $values) {
            foreach ($values as $value) {
                $parameterParts[] = oauthUrlencode($key) . '=' . oauthUrlencode($value);
            }
        }
        return implode('&', $parameterParts);
    }


    function getSignatureBasestring($request, $url, $parameters)
    {
        return oauthUrlencode(strtoupper($request)) . '&' .
                  oauthUrlencode($url) . '&' .
                  oauthUrlencode($parameters);
    }

    function sign($baseString, $consumerSecret, $tokenSecret)
    {
        $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($tokenSecret);
        echo "<br>$signingKey<br>";
        // 8. Calculer la signature HMAC‑SHA1 puis l’encoder en base64
        $rawSignature = hash_hmac('sha1', $baseString, $signingKey, true);
        return base64_encode($rawSignature);
    }

    function buildAuthHeader($baseUrl, $queryParams, $Nonce = NULL, $timeStamp= NULL)
    {
        // 2. Paramètres OAuth obligatoires
        $oauthParams = $this->oauthgetParameters($this->consumerKey, $Nonce, $timeStamp);
        if ($this->tokenSecret != NULL) {
            $oauthParams['oauth_token'] = $this->tokenSecret->oauth_token;
        }
        $parameterString = $this->prepareParameters($oauthParams, $queryParams);

        // 6. Construire la base string
        $baseString = $this->getSignatureBasestring($this->method , $baseUrl, $parameterString);

        echo "<br>basestring :$baseString<br>";

        // 7. Construire la signing key
        $oAuthSignature = $this->sign($baseString, $this->consumerSecret, ($this->tokenSecret==NULL)?'' : $this->tokenSecret->oauth_token_secret);
        
        // 9. Ajouter la signature aux paramètres OAuth
        $oauthParams['oauth_signature'] = $oAuthSignature;

        // 10. Créer l’en‑tête Authorization
        $headerParts = [];
        foreach ($oauthParams as $k => $v) {
            $headerParts[] = $k . '=' . oauthUrlencode($v) . '';
        }
        $authHeader = array("Authorization: OAuth " . implode(', ', $headerParts));
        echo "header:". print_r($authHeader, 1);
        return $authHeader;
    }
}