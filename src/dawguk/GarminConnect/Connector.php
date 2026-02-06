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

use Exception;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Connector
{
   /**
    * @var null|resource
    */
    private $objCurl = null;
    private $arrCurlInfo = array();
    private $strCookieDirectory = '';

    private $agents = array(
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:7.0.1) Gecko/20100101 Firefox/7.0.1',
    'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1.9) Gecko/20100508 SeaMonkey/2.0.4',
    'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0; en-US)',
    'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1'
    );


   /**
    * @var array
    */
    private $arrCurlOptions = array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_COOKIESESSION => false,
      CURLOPT_AUTOREFERER => true,
      CURLOPT_VERBOSE => false,
      CURLOPT_FRESH_CONNECT => true,
      CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:61.0) Gecko/20100101 Firefox/61.0',
      CURLOPT_ENCODING => 'gzip'
      //CURLOPT_SSLVERSION => 6
    );

   /**
    * @var int
    */
    private $intLastResponseCode = -1;

   /**
    * @var string
    */
    private $strCookieFile = '';

    private $log = false;
    private $logfile = NULL;
    private $count = 0;
    function clean_log(){
        if (file_exists($this->logfile)) {
            unlink($this->logfile);
        }
        $count = 0;
        while(file_exists($this->logfile . "." . $count . ".html")) {
                unlink($this->logfile  . "." . $count . ".html");
                $count = $count + 1;
        }
    }

    function log($msg, $html = false){
        if ($this->log != NULL) {
            if ($html) {
                $htmlfile = $this->logfile . "." . $this->count . ".html";
				$fp = fopen($htmlfile, "a");
				$this->count = $this->count + 1;
				fwrite($fp, $msg);
                $this->log->warning("fulle log here" . $htmlfile);
			} else {
                $this->log->warning($msg);
            }
        }
    }
   /**
    * @param string $strUniqueIdentifier
    * @throws Exception
    */
   public function __construct($strUniqueIdentifier, $logfile=NULL) {
        $this->logfile = $logfile;
        if ($logfile != NULL) {
            $format       = "[%datetime%] %channel%.%level_name%: %message% %context.user% %extra.ip%\n";
            // the default date format is "Y-m-d\TH:i:sP"
            $dateFormat = "Y n j, g:i a";
            $formatter = new LineFormatter($format, $dateFormat);
            $this->log = new Logger('Connector');
            $this->count = 1;
            $handler = new RotatingFileHandler(
               $logfile,   // chemin de base
                14,                        // maxFiles
                Logger::DEBUG              // niveau
            );
            $handler->setFormatter($formatter);
            $this->log->pushHandler($handler);
            $this->logfile = $logfile;
        }
        $this->strCookieDirectory = dirname(__FILE__);
        if (strlen(trim($strUniqueIdentifier)) == 0) {
            throw new Exception("Identifier isn't valid");
        }
        $this->strCookieFile = $this->strCookieDirectory . DIRECTORY_SEPARATOR . "GarminCookie_" . $strUniqueIdentifier;
        $this->refreshSession();
    }

   /**
    * Create a new curl instance
    */
    public function refreshSession()
    {
        $this->log("resfresh session");
        $this->objCurl = curl_init();
        $this->arrCurlOptions[CURLOPT_COOKIEJAR] = $this->strCookieFile;
        $this->arrCurlOptions[CURLOPT_COOKIEFILE] = $this->strCookieFile;
        curl_setopt_array($this->objCurl, $this->arrCurlOptions);
    }

   /**
    * @param string $strUrl
    * @param array $arrParams
    * @param bool $bolAllowRedirects
    * @return mixed
    */
    public function get($strUrl, $arrParams = array(), $bAllowRedirects = true, $arHeader= array())
    {
        if (null !== $arrParams && count($arrParams)) {
            $strUrl .= '?' . http_build_query($arrParams);
        }
        $this->log("get:" . $strUrl);

        curl_setopt($this->objCurl, CURLOPT_HTTPHEADER,
            //array_merge(array(
            //            'NK: NT'),
                        $arHeader//)
        );
        $this->log("headers:" . print_r($arHeader, true));
        curl_setopt($this->objCurl, CURLOPT_URL, $strUrl);
        curl_setopt($this->objCurl, CURLOPT_FOLLOWLOCATION, (bool)$bAllowRedirects);
        curl_setopt($this->objCurl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($this->objCurl, CURLOPT_USERAGENT, 'GCM-iOS-5.7.2.1'); 
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
     * @param string|null $strReferer
     * @param array $headers
     * @param string|null $rawPayload
     * @return mixed
     */
    public function post($strUrl, $arrParams = array(), $arrData = array(), $bolAllowRedirects = true, $strReferer = null, $headers = array(), $rawPayload = null)
    {
        if (empty($headers)) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if (!empty($rawPayload)) {
            curl_setopt($this->objCurl, CURLOPT_POSTFIELDS, $rawPayload);
            $headers[] = 'Content-Length: ' . strlen($rawPayload);
        }

        if ($arrData !== null && count($arrData)) {
            curl_setopt($this->objCurl, CURLOPT_POSTFIELDS, http_build_query($arrData));
        }

        if (null !== $strReferer) {
            curl_setopt($this->objCurl, CURLOPT_REFERER, $strReferer);
        }

        if (! empty($arrParams)) {
            $strUrl .= '?' . http_build_query($arrParams);
        }
        curl_setopt($this->objCurl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->objCurl, CURLOPT_HEADER, false);
        curl_setopt($this->objCurl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($this->objCurl, CURLOPT_FOLLOWLOCATION, (bool)$bolAllowRedirects);
        curl_setopt($this->objCurl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->objCurl, CURLOPT_USERAGENT, 'GCM-iOS-5.7.2.1');
        curl_setopt($this->objCurl, CURLOPT_VERBOSE, false);

        curl_setopt($this->objCurl, CURLOPT_URL, $strUrl);

        $strResponse = curl_exec($this->objCurl);
        $this->arrCurlInfo = curl_getinfo($this->objCurl);
        $this->intLastResponseCode = (int)$this->arrCurlInfo['http_code'];
        $this->log("post:" . $strUrl .  print_r($arrData, true) ."\n" . $this->intLastResponseCode);
        $this->log("post:=>");
        $this->log($strResponse, true);
        return $strResponse;
    }

    /**
     * @param $strUrl
     * @return bool|string
     */
    public function delete($strUrl)
    {
        curl_setopt($this->objCurl, CURLOPT_HEADER, false);
        curl_setopt($this->objCurl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($this->objCurl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->objCurl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->objCurl, CURLOPT_VERBOSE, false);

        curl_setopt($this->objCurl, CURLOPT_HTTPHEADER, array(
            'NK: NT',
            'X-HTTP-Method-Override: DELETE'
        ));

        curl_setopt($this->objCurl, CURLOPT_URL, $strUrl);

        $strResponse = curl_exec($this->objCurl);
        $this->arrCurlInfo = curl_getinfo($this->objCurl);
        $this->intLastResponseCode = (int)$this->arrCurlInfo['http_code'];
        return $strResponse;
    }

   /**
    * @return array
    */
    public function getCurlInfo()
    {
        return $this->arrCurlInfo;
    }

   /**
    * @return int
    */
    public function getLastResponseCode()
    {
        return $this->intLastResponseCode;
    }

   /**
    * Removes the cookie
    */
    public function clearCookie()
    {
        if (file_exists($this->strCookieFile)) {
            unlink($this->strCookieFile);
        }
    }

   /**
    * Closes curl and then clears the cookie.
    */
    public function cleanupSession()
    {
        curl_close($this->objCurl);
        $this->clearCookie();
    }
}
