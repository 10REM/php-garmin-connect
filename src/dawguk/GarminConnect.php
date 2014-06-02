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

class GarminConnect {

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
   private $objConnector = NULL;

   /**
    * Performs some essential setup
    *
    * @param array $arrCredentials
    * @throws \Exception
    */
   public function __construct(array $arrCredentials = array()) {

      if (!isset($arrCredentials['username'])) {
         throw new \Exception("Username credential missing");
      }

      $this->strUsername = $arrCredentials['username'];
      unset($arrCredentials['username']);

      if (!isset($arrCredentials['identifier'])) {
         throw new \Exception("Identifier credential missing");
      }

      $intIdentifier = $arrCredentials['identifier'];
      unset($arrCredentials['identifier']);

      $this->objConnector = new Connector($intIdentifier);

      // If we can validate the cached auth, we don't need to do anything else
      if ($this->checkCookieAuth($this->strUsername)) {
         return;
      }

      if (!isset($arrCredentials['password'])){
         throw new \Exception("Password credential missing");
      }

      $this->strPassword = $arrCredentials['password'];
      unset($arrCredentials['password']);

      $this->authorize($this->strUsername, $this->strPassword);

   }

   /**
    * Try to read the username from the API - if successful, it means we have a valid cookie, and we don't need to auth
    *
    * @param $strUsername
    * @return bool
    */
   private function checkCookieAuth($strUsername) {
      if (strlen($this->getUsername()) == 0 || $strUsername != $this->getUsername()) {
         $this->objConnector->clearCookie();
         return FALSE;
      } else {
         return TRUE;
      }
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
   private function authorize($strUsername, $strPassword) {

      $arrParams = array(
         'service' => "http://connect.garmin.com/post-auth/login",
         'clientId' => 'GarminConnect',
         'consumeServiceTicket' => "false"
      );
      $strResponse = $this->objConnector->get("https://sso.garmin.com/sso/login", $arrParams);
      if ($this->objConnector->getLastResponseCode() != 200) {
         throw new AuthenticationException(sprintf("SSO prestart error (code: %d, message: %s)", $this->objConnector->getLastResponseCode() , $strResponse));
      }

      $arrData = array(
         "username" => $strUsername,
         "password" => $strPassword,
         "_eventId" => "submit",
         "embed" => "true",
         "displayNameRequired" => "false"
      );

      preg_match("/name=\"lt\"\s+value=\"([^\"]+)\"/", $strResponse, $arrMatches);
      if (!isset($arrMatches[1])) {
         throw new AuthenticationException("\"lt\" value wasn't found in response");
      }

      $arrData['lt'] = $arrMatches[1];

      $strResponse = $this->objConnector->post("https://sso.garmin.com/sso/login", $arrParams, $arrData, FALSE);
      preg_match("/ticket=([^']+)'/", $strResponse, $arrMatches);

      if (!isset($arrMatches[1])) {
         throw new AuthenticationException("Ticket value wasn't found in response - looks like the authentication failed.");
      }

      $strTicket = $arrMatches[1];
      $arrParams = array(
         'ticket' => $strTicket
      );

      $this->objConnector->post('http://connect.garmin.com/post-auth/login', $arrParams, null, FALSE);
      if ($this->objConnector->getLastResponseCode() != 302) {
         throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
      }

      // should only exist if the above response WAS a 302 ;)
      $strRedirectUrl = $this->objConnector->getCurlInfo()['redirect_url'];

      $this->objConnector->get($strRedirectUrl, null, null, TRUE);
      if ($this->objConnector->getLastResponseCode() != 302) {
         throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
      }

   }

   /**
    * @return mixed
    * @throws UnexpectedResponseCodeException
    */
   public function getActivityTypes() {
      $strResponse = $this->objConnector->get('http://connect.garmin.com/proxy/activity-service-1.2/json/activity_types', null, null, FALSE);
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
    * @throws UnexpectedResponseCodeException
    * @return mixed
    */
   public function getActivityList($intStart = 0, $intLimit = 10) {

      $arrParams = array(
         'start' => $intStart,
         'limit' => $intLimit
      );

      $strResponse = $this->objConnector->get('http://connect.garmin.com/proxy/activity-search-service-1.0/json/activities', $arrParams, null, FALSE);
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
   public function getActivitySummary($intActivityID) {
      $strResponse = $this->objConnector->get("http://connect.garmin.com/proxy/activity-service-1.3/json/activity/" . $intActivityID);
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
   public function getActivityDetails($intActivityID) {
      $strResponse = $this->objConnector->get("http://connect.garmin.com/proxy/activity-service-1.3/json/activityDetails/" . $intActivityID);
      if ($this->objConnector->getLastResponseCode() != 200) {
         throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
      }
      $objResponse = json_decode($strResponse);
      return $objResponse;
   }

   /**
    * Retrieves the data file for the activity
    *
    * @param string $strType
    * @param $intActivityID
    * @throws GarminConnect\exceptions\UnexpectedResponseCodeException
    * @throws \Exception
    * @return mixed
    */
   public function getDataFile($strType, $intActivityID) {

      switch ($strType) {

         case self::DATA_TYPE_GPX:
         case self::DATA_TYPE_TCX:
         case self::DATA_TYPE_GOOGLE_EARTH:
            break;

         default:
            throw new \Exception("Unsupported data type");

      }

      $strResponse = $this->objConnector->get("http://connect.garmin.com/proxy/activity-service-1.1/" . $strType . "/activity/" . $intActivityID . "?full=true");
      if ($this->objConnector->getLastResponseCode() != 200) {
         throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
      }
      return $strResponse;
   }

   /**
    * @return mixed
    * @throws UnexpectedResponseCodeException
    */
   public function getUsername() {
      $strResponse = $this->objConnector->get('http://connect.garmin.com/user/username');
      if ($this->objConnector->getLastResponseCode() != 200) {
         throw new UnexpectedResponseCodeException($this->objConnector->getLastResponseCode());
      }
      $objResponse = json_decode($strResponse);
      return $objResponse->username;
   }

}
