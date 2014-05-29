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

class GarminConnect {

   private $strUsername = '';
   private $strPassword = '';
   private $objConnector = NULL;

   /**
    * @param array $arrCredentials
    * @throws \Exception
    */
   public function __construct(array $arrCredentials = array()) {


      if (!isset($arrCredentials['username'])) {
         throw new \Exception("Username credential missing");
      }

      $this->strUsername = $arrCredentials['username'];
      unset($arrCredentials['username']);

      $this->objConnector = new Connector($this->strUsername);

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

   public function getActivityList($arrParams) {
      $strResponse = $this->objConnector->get('http://connect.garmin.com/proxy/activity-search-service-1.0/json/activities', $arrParams, null, FALSE);
      if ($this->objConnector->getLastResponseCode() != 200) {
         throw new \Exception("Unexpected response code " . $this->objConnector->getLastResponseCode());
      }
      $objResponse = json_decode($strResponse);
      return $objResponse;
   }

   /**
    * Try to read the username from the API - if successful, it means we have a valid cookie, and we don't need to re-auth
    *
    * @param $strUsername
    * @return bool
    */
   private function checkCookieAuth($strUsername) {
      $objResponse = json_decode($this->objConnector->get('http://connect.garmin.com/user/username'));
      if (strlen((string)$objResponse->username) == 0) {
         $this->objConnector->clearCookie();
         return FALSE;
      } else {
         return TRUE;
      }
   }

   private function authorize($strUsername, $strPassword) {

      $arrParams = array(
         'service' => "http://connect.garmin.com/post-auth/login",
         'clientId' => 'GarminConnect',
         'consumeServiceTicket' => "false"
      );
      $strResponse = $this->objConnector->get("https://sso.garmin.com/sso/login", $arrParams);
      if ($this->objConnector->getLastResponseCode() != 200) {
         throw new \Exception(sprintf("SSO prestart error (code: %d, message: %s)", $this->objConnector->getLastResponseCode() , $strResponse));
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
         throw new \Exception("lt value wasn't found in response");
      }

      $arrData['lt'] = $arrMatches[1];

      $strResponse = $this->objConnector->post("https://sso.garmin.com/sso/login", $arrParams, $arrData, FALSE);
      preg_match("/ticket=([^']+)'/", $strResponse, $arrMatches);

      if (!isset($arrMatches[1])) {
         throw new \Exception("Ticket value wasn't found in response");
      }

      $strTicket = $arrMatches[1];
      $arrParams = array(
         'ticket' => $strTicket
      );

      $this->objConnector->post('http://connect.garmin.com/post-auth/login', $arrParams, null, FALSE);
      if ($this->objConnector->getLastResponseCode() != 302) {
         throw new \Exception("This is not the 302 you are looking for ...");
      }

      // should only exist if the above response WAS a 302 ;)
      $strRedirectUrl = $this->objConnector->getCurlInfo()['redirect_url'];

      $this->objConnector->get($strRedirectUrl, null, null, TRUE);
      if ($this->objConnector->getLastResponseCode() != 302) {
         throw new \Exception("This is still not the 302 you are looking for ...");
      }

   }

   public function getActivityTypes() {
      // get from: http://connect.garmin.com/proxy/activity-service-1.2/json/activity_types
   }

}

require_once 'GarminConnect/Connector.php';

$arrCredentials = array(
   'username' => 'dave.wilcock@gmail.com',
   'password' => 'kempson2009'
);

$objGarminConnect = new GarminConnect($arrCredentials);

$arrParams = array(
   'start' => 0,
   'limit' => 1
);
$objResults = $objGarminConnect->getActivityList($arrParams);
foreach($objResults->results->activities as $objActivity) {
   $intActivityId = (int)$objActivity->activity->activityId;
   $strTCXUrl = "http://connect.garmin.com/proxy/activity-service-1.1/tcx/activity/" . $intActivityId . "?full=true";
   file_put_contents("/tmp/" . $intActivityId . ".tcx", fopen($strTCXUrl, "r"));
}