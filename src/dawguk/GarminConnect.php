<?php
/**
 * GarminConnect.php
 *
 * LICENSE: THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author David Wilcock <dave.wilcock@gmail.com>
 * @copyright David Wilcock &copy; 2014
 * @package
 */

namespace dawguk;

use dawguk\GarminConnect\Connector;
use dawguk\GarminConnect\exceptions\AuthenticationException;
use dawguk\GarminConnect\exceptions\UnexpectedResponseCodeException;
use dawguk\Auth1Sign;
use Exception;

class GarminConnect
{
    const DATA_TYPE_FIT = 'fit';
    const DATA_TYPE_TCX = 'tcx';
    const DATA_TYPE_GPX = 'gpx';
    const DATA_TYPE_GOOGLE_EARTH = 'kml';

    /**
     * @var string
     */
    private $strUsername = '';

    /**
     * @var string
     */
    private $strPassword = '';

    /**
     * @var GarminConnect\Connector|null
     */
    private $objConnector = null;
    
    private $oAuth1Token = null;
    private $oAuth2Token = null;
    private $consumer_key = null;
    private $consumer_secret = null;
    private $tokenstore = null;

    /**
     * Performs some essential setup
     *
     * @param array $arrCredentials
     * @throws Exception
     */
    public function __construct(array $arrCredentials = array(), $log=NULL)
    {
        if (!isset($arrCredentials['username'])) {
            throw new Exception("Username credential missing");
        }

        $this->strUsername = $arrCredentials['username'];
        $intIdentifier = md5($this->strUsername);

        $this->objConnector = new Connector($intIdentifier, $log);
        //clear previous cookie
        $this->objConnector->clearCookie();

        if (!isset($arrCredentials['password'])) {
            throw new Exception("Password credential missing");
        }

        $this->strPassword = $arrCredentials['password'];

        if (!isset($arrCredentials['consumer_key'])) {
            throw new Exception("consumer_key credential missing");
        }
        $this->consumer_key = $arrCredentials['consumer_key'];

        if (!isset($arrCredentials['consumer_key'])) {
            throw new Exception("consumer_secret credential missing");
        }
        $this->consumer_secret = $arrCredentials['consumer_secret'];

        if (isset($arrCredentials['tokenstore'])) {
            $this->tokenstore = $arrCredentials['tokenstore'];
        }

        //unset all in array
        foreach ($arrCredentials as $k => $v) {
            unset($arrCredentials[$k]); 
        }
    }


    private function getAuth1Token($ticket)
    {
        $baseUrl = "https://connectapi.garmin.com/oauth-service/oauth/preauthorized";
        $loginUrl = "https://sso.garmin.com/sso/embed";
        $auth1Params = array(
            "ticket" => $ticket,
            "login-url" => $loginUrl, 
            'accepts-mfa-tokens' => 'true');
        $oAuth1Sign = new Auth1Sign($this->consumer_key,
                                    $this->consumer_secret);
        $authlHeader = $oAuth1Sign->buildAuthHeader($baseUrl, $auth1Params);
        $strResponse = $this->objConnector->get($baseUrl, $auth1Params, true, $authlHeader);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new AuthenticationException(sprintf(
                "getAuth1Token error (code: %d, message: %s)",
                $this->objConnector->getLastResponseCode(),
                $strResponse
            ));
        }
        $this->objConnector->log($strResponse, 1);
        parse_str($strResponse, $oAuth1Token);
        $this->objConnector->log(print_r($oAuth1Token, 1));
        return (object)$oAuth1Token;
    }

    /**
     * retrieves the oauth2 token
     *
     * @param string $oauth_token the token given by oauth1 steps
     */
    private function getAuth2Token($oauth_token)
    {
        $baseUrl = "https://connectapi.garmin.com/oauth-service/oauth/exchange/user/2.0";
        $auth1Params = array();
        $oAuth1Sign = new Auth1Sign($this->consumer_key,
                                    $this->consumer_secret,
                                   "POST",
                                   $oauth_token);
        $authlHeader = $oAuth1Sign->buildAuthHeader($baseUrl, $auth1Params);
        $authlHeader[] = 'Content-Type: application/x-www-form-urlencoded';
        $strResponse = $this->objConnector->post($baseUrl, $auth1Params, array(), true, NULL, $authlHeader);
        $this->objConnector->log($strResponse, 1);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new AuthenticationException(sprintf(
                "getAuth2Token error (code: %d, message: %s)",
                $this->objConnector->getLastResponseCode(),
                $strResponse
            ));
        }
        $oauth2Data = json_decode($strResponse, true);
        $oauth2Data['expires_at'] = $oauth2Data['expires_in'] + time();
        $oauth2Data['refresh_token_expires_at'] = $oauth2Data['refresh_token_expires_in'] + time();
        return $oauth2Data ;
    }

    public function hasValidTokens()
    {
        $valid = false;
        if (isset($this->oAuth1Token) && isset($this->oAuth2Token))
        {
            if (array_key_exists('expires_at', $this->oAuth2Token) && ($this->oAuth2Token['expires_at'] > time()))
            {
                $this->objConnector->log("oAuth2 is still valid:" .  date('d/m/Y H:i:s', $this->oAuth2Token['expires_at']));
                $valid = true;
            }
            else 
            {
                $this->objConnector->log("oAuth2 expired or no expires_at");
            }
        }
        return $valid;
    }

    /**
     * Because there doesn't appear to be a nice "API" way to authenticate with Garmin Connect, we have to effectively spoof
     * a browser session using some pretty high-level scraping techniques. The connector object does all of the HTTP
     * work, and is effectively a wrapper for CURL-based session handler (via CURLs in-built cookie storage).
     *
     * @param string $strUsername
     * @param string $strPassword
     * @throws AuthenticationException
     * @throws UnexpectedResponseCodeException
     */
    public function login()
    {
        //tries to load the credentials
        if (isset($this->tokenstore)) 
        {
            $oauth1_file = $this->tokenstore . DIRECTORY_SEPARATOR . "oauth1_token.json";
            if (file_exists($oauth1_file))
            {
                $json = file_get_contents($oauth1_file);
                $data = json_decode($json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    die('Erreur JSON : ' . json_last_error_msg());
                }
                $this->oAuth1Token = $data;
            }
            $oauth2_file = $this->tokenstore . DIRECTORY_SEPARATOR . "oauth2_token.json";
            if (file_exists($oauth2_file))
            {
                $json = file_get_contents($oauth2_file);
                $data = json_decode($json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    die('Erreur JSON : ' . json_last_error_msg());
                }
                $this->oAuth2Token = $data;
            }
        }
        if ($this->hasValidTokens()) return;
        $strUsername = $this->strUsername;
        $strPassword =  $this->strPassword;
        $SSO = "https://sso.garmin.com/sso";
        $SSO_EMBED = $SSO . "/embed";
        $ssoEmbedParams = array(
            "id" => "gauth-widget",
            "embedWidget" => "true",
            "gauthHost" => $SSO,
        );
        $arrParams = 
            array_merge ($ssoEmbedParams,
                        array(
                        "gauthHost" => $SSO_EMBED,
                        "service" => $SSO_EMBED,
                        "source" =>$SSO_EMBED,
                        "redirectAfterAccountLoginUrl" => $SSO_EMBED,
                        "redirectAfterAccountCreationUrl" => $SSO_EMBED
                        )
            );

        $strResponse = $this->objConnector->get($SSO . "/embed", $ssoEmbedParams);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new AuthenticationException(sprintf(
                "SSO cookies prestart error (code: %d, message: %s)",
                $this->objConnector->getLastResponseCode(),
                $strResponse
            ));
        }
        $this->objConnector->log("embed:" . $strResponse, true);
        $strResponse = $this->objConnector->get($SSO . "/signin", $arrParams);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new AuthenticationException(sprintf(
                "SSO prestart error (code: %d, message: %s)",
                $this->objConnector->getLastResponseCode(),
                $strResponse
            ));
        }

        preg_match("/name=\"_csrf\" value=\"(.*)\"/", $strResponse, $arrCsrfMatches);
        if (!isset($arrCsrfMatches[1])) {
            throw new AuthenticationException("Unable to find CSRF input in login form");
        }

        $strSigninUrl = $SSO . "/signin?" . http_build_query($arrParams);
        $arrData = array(
            "username" => $strUsername,
            "password" => $strPassword,
            "_eventId" => "submit",
            "embed" => "true",
            "displayNameRequired" => "false",
            "_csrf" => $arrCsrfMatches[1],
        );

        $strResponse = $this->objConnector->post($SSO . "/signin", $arrParams, $arrData, true, $strSigninUrl);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new AuthenticationException(sprintf(
                "SSO sigin error (code: %d, message: %s)",
                $this->objConnector->getLastResponseCode(),
                $strResponse
            ));
        }
        preg_match("/ticket=([^\"]+)\"/", $strResponse, $arrMatches);

        if (!isset($arrMatches[1])) {
            $strMessage = "Authentication failed - please check your credentials (".$strResponse.")";

            preg_match("/locked/", $strResponse, $arrLocked);

            if (isset($arrLocked[0])) {
                $strMessage = "Authentication failed, and it looks like your account has been locked. Please access https://connect.garmin.com to unlock";
            }

            $this->objConnector->cleanupSession();
            throw new AuthenticationException($strMessage);
        }

        $strTicket = rtrim($arrMatches[1], '"');
        $arrParams = array(
            'ticket' => $strTicket
        );
        $this->objConnector->log("ticket" . print_r($strTicket, 1));
        $this->objConnector->refreshSession();
        $this->oAuth1Token = $this->getAuth1Token($strTicket);
        $this->oAuth2Token = $this->getAuth2Token($this->oAuth1Token);
        $this->objConnector->log("oAuth1" . print_r($this->oAuth1Token, 1));
        $this->objConnector->log("oAuth2" . print_r($this->oAuth2Token, 1));
        if (isset($this->tokenstore)) 
        {
            if (!is_dir($this->tokenstore)) {
                mkdir($this->tokenstore);
            }
            file_put_contents($oauth1_file, json_encode($this->oAuth1Token));
            file_put_contents($oauth2_file, json_encode($this->oAuth2Token));
        }
    }
    
    /**
     * retrieves the oauth2 token
     *
     * @param string $oauth_token the token given by oauth1 steps
     */
    private function getwithBearer($url, $arrParams = array())
    {
        if ($this->oAuth2Token == NULL) {
            echo "oAuth2Token is not set";
            return "";
        }
        $arrHeader = array("Authorization:  Bearer ". $this->oAuth2Token["access_token"]);
        $strResponse = $this->objConnector->get(
            $url,
            $arrParams,
            true,
            $arrHeader
        );
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        return $strResponse;
    }


    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityTypes()
    {
        $strResponse = $this->getwithBearer(
            'https://connectapi.garmin.com/activity-service/activity/activityTypes');
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Gets a list of activities
     *
     * @param integer $intStart
     * @param integer $intLimit
     * @param null $strActivityType
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityList($intStart = 0, $intLimit = 10, $strActivityType = null)
    {
        $arrParams = array(
            'start' => $intStart,
            'limit' => $intLimit
        );

        if (null !== $strActivityType) {
            $arrParams['activityType'] = $strActivityType;
        }
        
        $strResponse = $this->getwithBearer(
            'https://connectapi.garmin.com/activitylist-service/activities/search/activities',
            $arrParams);
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Gets a list of workouts
     *
     * @param integer $intStart
     * @param integer $intLimit
     * @param bool $myWorkoutsOnly
     * @param bool $sharedWorkoutsOnly
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getWorkoutList($intStart = 0, $intLimit = 10, $myWorkoutsOnly = true, $sharedWorkoutsOnly = false)
    {
        $arrParams = array(
            'start' => $intStart,
            'limit' => $intLimit,
            'myWorkoutsOnly' => $myWorkoutsOnly,
            'sharedWorkoutsOnly' => $sharedWorkoutsOnly
        );

        $strResponse = $this->getwithBearer(
            'https://connectapi.garmin.com/workout-service/workouts',
            $arrParams
        );

        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Create a workout from JSON data
     *
     * @param $data
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function createWorkout($data)
    {
        if (empty($data)) {
            throw new Exception('Data must be supplied to create a new workout.');
        }

        $headers = array(
            'NK: NT',
            'Content-Type: application/json'
        );

        $strResponse = $this->objConnector->post(
            'https://connectapi.garmin.com/workout-service/workout',
            array(),
            array(),
            true,
            'https://connect.garmin.com/modern/workout/create/running',
            $headers,
            $data
        );

        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Delete a workout based upon the workout ID
     *
     * @param $id
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function deleteWorkout($id)
    {
        if (empty($id)) {
            throw new Exception('Workout ID must be supplied to delete a workout.');
        }

        $strResponse = $this->objConnector->delete(
            'https://connectapi.garmin.com/workout-service/workout/' . $id
        );

        if ($this->objConnector->getLastResponseCode() != 204) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Creates a note for a step from JSON data
     *
     * @param $data
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function createStepNote($stepID, $note, $workoutID)
    {
        if (empty($stepID) || empty($note) || empty($workoutID)) {
            throw new Exception('Data must be supplied to create a new workout.');
        }

        $headers = array(
            'NK: NT',
            'Content-Type: application/json'
        );

        $data = json_encode(array('workoutId' => $workoutID, 'stepId' => $stepID, 'stepNote' => $note));

        $strResponse = $this->objConnector->post(
            'https://connectapi.garmin.com/workout-service/workout/' . $workoutID. '/step/' . $stepID . '/note',
            array(),
            array(),
            true,
            'https://connect.garmin.com/modern/workout/' . $workoutID,
            $headers,
            $data
        );

        if ($this->objConnector->getLastResponseCode() != 204) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Schedule a workout on the calendar
     *
     * @param $id
     * @param $payload
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function scheduleWorkout($id, $payload)
    {
        $headers = array(
            'NK: NT',
            'Content-Type: application/json'
        );

        if (empty($id)) {
            throw new Exception('Workout ID must be supplied to delete a workout.');
        }

        $strResponse = $this->objConnector->post(
            'https://connectapi.garmin.com/workout-service/schedule/' . $id,
            array(),
            array(),
            true,
            'https://connect.garmin.com/modern/calendar',
            $headers,
            $payload
        );

        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Gets the summary information for the activity
     *
     * @param integer $intActivityID
     * @return mixed
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     */
    public function getActivitySummary($intActivityID)
    {
        $strResponse = $this->getwithBearer("https://connectapi.garmin.com/activity-service/activity/" . $intActivityID);
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Gets the detailed information for the activity
     *
     * @param integer $intActivityID
     * @return mixed
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     */
    public function getActivityDetails($intActivityID)
    {
        $strResponse = $this->getwithBearer("https://connect.garmin.com/proxy/activity-service/details/" . $intActivityID);
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Gets the extended details for the activity
     *
     * @param $intActivityID
     * @return mixed
     */
    public function getExtendedActivityDetails($intActivityID)
    {
        $strResponse = $this->getwithBearer("https://connectapi.garmin.com/activity-service/activity/" . $intActivityID . "/details?maxChartSize=1000&maxPolylineSize=1000");
        return json_decode($strResponse);
    }

    /**
     * Retrieves the data file for the activity
     *
     * @param string $strType
     * @param $intActivityID
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     * @throws Exception
     * @return mixed
     */
    public function getDataFile($strType, $intActivityID)
    {
        switch ($strType) {
            case self::DATA_TYPE_GPX:
            case self::DATA_TYPE_TCX:
            case self::DATA_TYPE_GOOGLE_EARTH:
                $strUrl = "https://connectapi.garmin.com/download-service/export/" . $strType . "/activity/" . $intActivityID;
                break;
            case self::DATA_TYPE_FIT:
                $strUrl = "https://connectapi.garmin.com/download-service/files/activity/" . $intActivityID;
                break;
            default:
                throw new Exception("Unsupported data type");
        }
        $strResponse = $this->getwithBearer($strUrl);
        return $strResponse;
    }

    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getUser()
    {
        $strResponse = $this->getwithBearer('https://connect.garmin.com/modern/currentuser-service/user/info');
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getUsername()
    {
        try {
            $objUser = $this->getUser();
        }
        catch(Exception $e)
		{
            echo "exception with get user" . print_r($e, true);
        }
        if (!$objUser) {
            return null;
        }
        return $objUser->username;
    }

    /**
     * Retrieves weight data
     *
     * @param string $strFrom
     * @param string $strUntil
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     * @throws Exception
     * @return mixed
     */
    public function getWeightData($strFrom = '2019-01-01', $strUntil = '2099-12-31')
    {
        $intDateFrom = (strtotime($strFrom) + 86400) * 1000;
        $intDateUntil = strtotime($strUntil) * 1000;

        $arrParams = array(
            'from' => $intDateFrom,
            'until' => $intDateUntil
        );

        $strResponse = $this->getwithBearer(
            'https://connectapi.garmin.com/userprofile-service/userprofile/personal-information/weightWithOutbound/',
            $arrParams
        );

        $objResponse = json_decode($strResponse, true);
        return $objResponse;
    }

    /**
     * Retrieves wellness data
     *
     * @param string $strFrom
     * @param string $strUntil
     * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
     * @throws Exception
     * @return array
     */
    public function getWellnessData($strFrom = NULL, $strUntil = NULL)
    {
        $arrParams = array();
        if (isset($strFrom)) {
            $arrParams['fromDate'] = $strFrom;
        }
        if (isset($strUntil)) {
            $arrParams['untilDate'] = $strUntil;
        }

        $strResponse = $this->getwithBearer(
            'https://connectapi.garmin.com/userstats-service/wellness/daily/' . $this->getUser()->displayName,
            $arrParams
        );

        $objResponse = json_decode($strResponse, true);
        return $objResponse;
    }
	
   /**
    * Retrieves sleep data
    *
    * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
    * @throws Exception
    * @return mixed
    */
    public function getSleepData()
    {
        $arrParams = Array();

        $strResponse = $this->getwithBearer(
            'https://connectapi.garmin.com/wellness-service/wellness/dailySleeps?limit=10&start=1',
            $arrParams
        );

        $objResponse = json_decode($strResponse, true);
        return $objResponse;
    }

}
