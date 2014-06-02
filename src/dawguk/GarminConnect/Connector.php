<?php
/**
 * Connector.php
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

namespace dawguk\GarminConnect;


class Connector {

   const COOKIE_DIRECTORY = '/tmp/';

   /**
    * @var null|resource
    */
   private $objCurl = NULL;
   private $arrCurlInfo = array();

   /**
    * @var array
    */
   private $arrCurlOptions = array(
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_SSL_VERIFYHOST => FALSE,
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_COOKIESESSION => FALSE,
      CURLOPT_AUTOREFERER => TRUE,
      CURLOPT_VERBOSE => FALSE
   );

   /**
    * @var int
    */
   private $intLastResponseCode = -1;

   /**
    * @var string
    */
   private $intUniqueIdentifier = '';

   /**
    * @param integer $intUniqueIdentifier
    * @throws \Exception
    */
   public function __construct($intUniqueIdentifier) {
      if (!is_int($intUniqueIdentifier)) {
         throw new \Exception("Identifier isn't an integer");
      }
      $this->intUniqueIdentifier = base_convert($intUniqueIdentifier, 10, 32);
      $this->objCurl = curl_init();
      $this->arrCurlOptions[CURLOPT_COOKIEJAR] = self::COOKIE_DIRECTORY . $this->intUniqueIdentifier;
      $this->arrCurlOptions[CURLOPT_COOKIEFILE] = self::COOKIE_DIRECTORY . $this->intUniqueIdentifier;
      curl_setopt_array($this->objCurl, $this->arrCurlOptions);
   }

   /**
    * @param string $strUrl
    * @param array $arrParams
    * @param bool $bolAllowRedirects
    * @return mixed
    */
   public function get($strUrl, $arrParams = array(), $bolAllowRedirects = TRUE) {
      if (count($arrParams)) {
         $strUrl .= '?' . http_build_query($arrParams);
      }

      curl_setopt($this->objCurl, CURLOPT_FRESH_CONNECT, TRUE);
      curl_setopt($this->objCurl, CURLOPT_URL, $strUrl);
      curl_setopt($this->objCurl, CURLOPT_FOLLOWLOCATION, (bool)$bolAllowRedirects);
      curl_setopt($this->objCurl, CURLOPT_CUSTOMREQUEST, 'GET');

      $strResponse = curl_exec($this->objCurl);
      $arrCurlInfo = curl_getinfo($this->objCurl);
      $this->intLastResponseCode = $arrCurlInfo['http_code'];
      return $strResponse;
   }

   /**
    * @param string $strUrl
    * @param array $arrParams
    * @param array $arrData
    * @param bool $bolAllowRedirects
    * @return mixed
    */
   public function post($strUrl, $arrParams = array(), $arrData = array(), $bolAllowRedirects = TRUE) {

      curl_setopt($this->objCurl, CURLOPT_HEADER, TRUE);
      curl_setopt($this->objCurl, CURLOPT_FRESH_CONNECT, TRUE);
      curl_setopt($this->objCurl, CURLOPT_FOLLOWLOCATION, (bool)$bolAllowRedirects);
      curl_setopt($this->objCurl, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($this->objCurl, CURLOPT_VERBOSE, FALSE);
      if (count($arrData)) {
         curl_setopt($this->objCurl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
         curl_setopt($this->objCurl, CURLOPT_POSTFIELDS, http_build_query($arrData));
      }
      $strUrl .= '?' . http_build_query($arrParams);

      curl_setopt($this->objCurl, CURLOPT_URL, $strUrl);

      $strResponse = curl_exec($this->objCurl);
      $this->arrCurlInfo = curl_getinfo($this->objCurl);
      $this->intLastResponseCode = (int)$this->arrCurlInfo['http_code'];
      return $strResponse;
   }

   /**
    * @return array
    */
   public function getCurlInfo() {
      return $this->arrCurlInfo;
   }

   /**
    * @return int
    */
   public function getLastResponseCode() {
      return $this->intLastResponseCode;
   }

   /**
    * Removes the cookie
    */
   public function clearCookie() {
      if (file_exists(self::COOKIE_DIRECTORY . $this->intUniqueIdentifier)) {
         unlink(self::COOKIE_DIRECTORY . $this->intUniqueIdentifier);
      }
   }

} 