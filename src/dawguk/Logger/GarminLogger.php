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

namespace dawguk\Logger;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class GarminLogger {
    private $log = false;
    private $logfile = NULL;
    private $count = 0;

    function __construct($logfile)
    {
        $this->logfile = $logfile;
        if ($logfile != NULL) {
            $format       = "[%datetime%] %channel%.%level_name%: %message% %context.user% %extra.ip%\n";
            // the default date format is "Y-m-d\TH:i:sP"
            $dateFormat = "Y n j, g:i a";
            $formatter = new LineFormatter($format, $dateFormat);
            $this->log = new Logger('Connector');
            $this->count = 1;
            $handler = new RotatingFileHandler(
               $logfile,                    // chemin de base
                14,                        // maxFiles
                Logger::DEBUG              // niveau
            );
            $handler->setFormatter($formatter);
            $this->log->pushHandler($handler);
            $this->logfile = $logfile;
        }
    }
    
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
}