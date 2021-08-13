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

    /**
     * Performs some essential setup
     *
     * @param array $arrCredentials
     * @throws Exception
     */
    public function __construct(array $arrCredentials = array())
    {
        if (!isset($arrCredentials['username'])) {
            throw new Exception("Username credential missing");
        }

        $this->strUsername = $arrCredentials['username'];
        unset($arrCredentials['username']);

        $intIdentifier = md5($this->strUsername);

        $this->objConnector = new Connector($intIdentifier);

        // If we can validate the cached auth, we don't need to do anything else

        if ($this->checkCookieAuth()) {
            return;
        }

        if (!isset($arrCredentials['password'])) {
            throw new Exception("Password credential missing");
        }

        $this->strPassword = $arrCredentials['password'];
        unset($arrCredentials['password']);

        $this->authorize($this->strUsername, $this->strPassword);
    }

    /**
     * Try to read the username from the API - if successful, it means we have a valid cookie, and we don't need to auth
     *
     * @return bool
     * @throws UnexpectedResponseCodeException
     */
    private function checkCookieAuth()
    {
        if (strlen(trim($this->getUsername())) == 0) {
            $this->objConnector->cleanupSession();
            $this->objConnector->refreshSession();
            return false;
        }
        return true;
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
    private function authorize($strUsername, $strPassword)
    {
        $arrParams = array(
            'service' => 'https://connect.garmin.com/modern/',
            'webhost' => 'https://connect.garmin.com',
            'source' => 'https://connect.garmin.com/en-US/signin',
            'clientId' => 'GarminConnect',
            'gauthHost' => 'https://sso.garmin.com/sso',
            'consumeServiceTicket' => 'false'
        );
        $strResponse = $this->objConnector->get("https://sso.garmin.com/sso/login", $arrParams);

        $strSigninUrl = "https://sso.garmin.com/sso/login?" . http_build_query($arrParams);

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

        $arrData = array(
            "username" => $strUsername,
            "password" => $strPassword,
            "_eventId" => "submit",
            "embed" => "true",
            "displayNameRequired" => "false",
            "_csrf" => $arrCsrfMatches[1],
        );

        $strResponse = $this->objConnector->post("https://sso.garmin.com/sso/login", $arrParams, $arrData, true, $strSigninUrl);
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

        $strTicket = rtrim($arrMatches[0], '"');
        $arrParams = array(
            'ticket' => $strTicket
        );

        $this->objConnector->post('https://connect.garmin.com/modern/', $arrParams, null, false);
        if ($this->objConnector->getLastResponseCode() != 302) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }

        // should only exist if the above response WAS a 302 ;)
        $arrCurlInfo = $this->objConnector->getCurlInfo();
        $strRedirectUrl = $arrCurlInfo['redirect_url'];

        $this->objConnector->get($strRedirectUrl, null, true);
        if (!in_array($this->objConnector->getLastResponseCode(), array(200, 302))) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }

        // Fires up a fresh CuRL instance, because of our reliance on Cookies requiring "a new page load" as it were ...
        $this->objConnector->refreshSession();
    }

    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityTypes()
    {
        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/modern/proxy/activity-service/activity/activityTypes',
            null,
            false
        );
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * Gets a list of activities
     *
     * @param integer $intStart
     * @param integer $intLimit
     * @param null $strActivityType
     * @param array $filters
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getActivityList($intStart = 0, $intLimit = 10, $strActivityType = null, $filters = array())
    {
        $arrParams = array(
            'start' => $intStart,
            'limit' => $intLimit
        );

        if (null !== $strActivityType) {
            $arrParams['activityType'] = $strActivityType;
        }

        if (!empty($filters)) {
            $arrParams = array_merge($arrParams, $filters);
        }

        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/modern/proxy/activitylist-service/activities/search/activities',
            $arrParams,
            true
        );

        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
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

        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/modern/proxy/workout-service/workouts',
            $arrParams,
            true
        );

        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
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
            'https://connect.garmin.com/modern/proxy/workout-service/workout',
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
            'https://connect.garmin.com/modern/proxy/workout-service/workout/' . $id
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
            'https://connect.garmin.com/modern/proxy/workout-service/workout/' . $workoutID. '/step/' . $stepID . '/note',
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
            'https://connect.garmin.com/modern/proxy/workout-service/schedule/' . $id,
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
        $strResponse = $this->objConnector->get("https://connect.garmin.com/modern/proxy/activity-service/activity/" . $intActivityID);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
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
        $strResponse = $this->objConnector->get("https://connect.garmin.com/proxy/activity-service/details/" . $intActivityID);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
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
        $strResponse = $this->objConnector->get("https://connect.garmin.com/modern/proxy/activity-service/activity/" . $intActivityID . "/details?maxChartSize=1000&maxPolylineSize=1000");
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
                $strUrl = "https://connect.garmin.com/modern/proxy/download-service/export/" . $strType . "/activity/" . $intActivityID;
                break;
            case self::DATA_TYPE_FIT:
                $strUrl = "https://connect.garmin.com/modern/proxy/download-service/files/activity/" . $intActivityID;
                break;
            default:
                throw new Exception("Unsupported data type");
        }

        $strResponse = $this->objConnector->get($strUrl);
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        return $strResponse;
    }

    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getUser()
    {
        $strResponse = $this->objConnector->get('https://connect.garmin.com/modern/currentuser-service/user/info');
        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse);
        return $objResponse;
    }

    /**
     * @return mixed
     * @throws UnexpectedResponseCodeException
     */
    public function getUsername()
    {
        $objUser = $this->getUser();
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

        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/modern/proxy/userprofile-service/userprofile/personal-information/weightWithOutbound/',
            $arrParams,
            true
        );

        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
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

        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/modern/proxy/userstats-service/wellness/daily/' . $this->getUser()->displayName,
            $arrParams,
            true
        );

        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
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

        $strResponse = $this->objConnector->get(
            'https://connect.garmin.com/modern/proxy/wellness-service/wellness/dailySleeps?limit=10&start=1',
            $arrParams,
            true
        );

        if ($this->objConnector->getLastResponseCode() != 200) {
            throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
        }
        $objResponse = json_decode($strResponse, true);
        return $objResponse;
    }

}
