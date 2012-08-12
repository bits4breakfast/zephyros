<?php
/*
$Id$
*/
class RouteParameters {
	private static $instance = null;

	public $method = "GET";
	public $controller = "homepage";
	public $action = "__default";
	public $id = null;
	public $format = "html";
	public $hasValidSession = false;
	public $mobile = false;
	public $userId = false;
	public $errorCode = 0;

	public static function init() {
		if ( self::$instance == null ) {
			self::$instance = new RouteParameters();
		}
		return self::$instance;
	}

}

class Router {
	private $p = null;
	private $startTime = 0;

	public function __construct() {
		$this->startTime = microtime(true);
		ob_start();
		session_start();

		$this->p = RouteParameters::init();

		if (isset($_POST["_method"])) {
			// POST -> UPDATE ~~~~~ PUT -> INSERT
			if ($_POST["_method"] != "POST" && $_POST["_method"] != "PUT" && $_POST["_method"] != "DELETE") {
				$this->__toError(Controller::ERROR_BAD_REQUEST);
			} else {
				$this->p->method = $_POST["_method"];
			}
		} else {
			$this->p->method = $_SERVER['REQUEST_METHOD'];
		}

		if (substr($_SERVER['SERVER_NAME'], 0, 2) == 'm.' || substr($_SERVER['SERVER_NAME'], 0, 10) == 'ymptest-m.') {
			$this->p->mobile = true;
		}

		if ( isset($_GET['controller']) ) {
			$this->p->controller = $_GET['controller'];
			$this->p->action = isset($_GET['action']) ? $_GET['action'] : '';
			$this->p->id = isset($_GET['id']) ? $_GET['id'] : 0;
		} else {
			$uri = substr($_SERVER["REDIRECT_URL"], 1);
			if ( strpos($uri, "?") !== false ) {
				$uri = explode("?", $uri);
				$uri = $uri[0];
			}

			/*if ( substr($uri, 0, 2) == 'm/' ) {
				$this->p->mobile = true;
				$uri = substr($uri, 2);
			}*/

			list($this->p->controller, $this->p->action, $this->p->id) = (array) array_pad(explode("/", $uri), 3, "");
		}

		foreach ( array('json') as $format ) {
			if ( strpos($this->p->id, ".".$format) !== false ) {
				$this->p->format = $format;
				$this->p->id = str_replace(".".$format, "", $this->p->id);
				break;
			} else if ( strpos($this->p->controller, ".".$format) !== false ) {
				$this->p->format = $format;
				$this->p->controller = str_replace(".".$format, "", $this->p->controller);
				break;
			} else if ( strpos($this->p->action, ".".$format) !== false ) {
				$this->p->format = $format;
				$this->p->action = str_replace(".".$format, "", $this->p->action);
				break;
			}
		}

		if ( is_numeric($this->p->action) ) {
			$this->p->id = (int) $this->p->action;
			$this->p->action = "";
		}

		if ( $this->p->id != null ) {
			if ( is_numeric($this->p->id) ) {
				$this->p->id = (int) $this->p->id;
			} else {
				$this->p->id = mysql_escape_string($this->p->id);
			}
		} else {
			$this->p->id = (int) $this->p->id;
		}

		if ( !isset(Config::$webservice) || ( isset(Config::$webservice) && !Config::$webservice) ) {
			if ( isset($_SESSION['userId']) ) {
				$this->p->hasValidSession = true;
			}
		} else {
			if ( $this->p->format == "html" ) {
				$this->p->format = "json";
			}

			if ( isset($_GET["sid"]) ) {
				$this->p->hasValidSession = true;
			}
		}

		$this->route();
	}

	public function __toError( $code = 0 ) {
		$this->p->errorCode = $code;
		new Controller( $this->p );
		exit();
	}

	public function route() {
		if ( !isset(Config::$webservice) || ( isset(Config::$webservice) && !Config::$webservice) ) {

			if ($this->p->controller == "") {
				$this->p->controller = "homepage";
			}

			if ( !$this->p->hasValidSession && !in_array($this->p->controller, Config::$doesntRequireAuthentication) ) {
				if ( $this->p->format == 'html' ) {
					header("Location: /".Config::$doesntRequireAuthentication[0]); // Il primo elemento dell'array viene considerato quello a cui rimandare l'utente nel caso di sessione mancante
				} else {
					$__c = new Controller( $this->p );
					$__c->response( 'ERROR' );
					$this->__toError( Controller::ERROR_UNAUTHORIZED );
				}
				exit;
			}
		} else {
			if ( !in_array($this->p->controller, Config::$doesntRequireAuthentication) && !$this->p->hasValidSession ) {
				$this->__toError(Controller::ERROR_UNAUTHORIZED);
			}

			if ($this->p->controller == "") {
				$this->__toError(Controller::ERROR_BAD_REQUEST);
			}
		}

		if ( file_exists(BaseConfig::BASE_PATH.'/application/controllers/'.Config::SUBDOMAIN.'/'.ucfirst($this->p->controller).'Controller.class.php') ) {
			$className = Config::SUBDOMAIN.'_';
		} else {
			$className = '';
		}

		if ( file_exists(BaseConfig::BASE_PATH.'/application/controllers/base/'.ucfirst($this->p->controller).'Controller.class.php') ) {
			include BaseConfig::BASE_PATH.'/application/controllers/base/'.ucfirst($this->p->controller).'Controller.class.php';
		} else if ( $className == '' ) {
			Logger::log(Logger::ERROR, "Invalid controller: " . $this->p->controller);
			$this->__toError(Controller::ERROR_NOT_FOUND);
		}

		if ( $className != '' ) {
			include BaseConfig::BASE_PATH.'/application/controllers/'.Config::SUBDOMAIN.'/'.ucfirst($this->p->controller).'Controller.class.php';
		}

		$className .= ucfirst($this->p->controller).'Controller';
		$__c = new $className($this->p);
		try {
			$__c->render();
		} catch (Exception $e) {
			Logger::logException($e);
			$this->__toError(Controller::ERROR_UNEXPECTED);
		}
	}

	public function __destruct() {
		if ( isset(Config::$donotTrack) && $this->p->action != '' ) {
			if ( Config::$donotTrack === true || isset(Config::$donotTrack[$this->p->controller][$this->p->action]) ) {
				return;
			}
		}
		$execTime = microtime(true)-$this->startTime;
		$memory = memory_get_peak_usage();
		$fp = fopen(Config::LOGS_PATH."/usage/".Config::SUBDOMAIN."_".@date("Y-m-d").".log", 'a');
		fputcsv($fp, array(@date("Y-m-d H:i:s"), $this->p->controller, $this->p->action, $this->p->id, $execTime, $memory));
		fclose($fp);
	}
}
$__r = new Router();
?>
