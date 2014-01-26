<?php
namespace bits4breakfast\zephyros;

class RouteParameters {
	private static $instance = null;

	public $method = "GET";
	public $controller = "events";
	public $action = "__default";
	public $id = null;
	public $format = "html";
	public $hasValidSession = false;
	public $mobile = false;
	public $userId = false;
	public $errorCode = 0;

	public static function init() {
		if ( self::$instance == null ) {
			self::$instance = new \zephyros\RouteParameters();
		}
		return self::$instance;
	}

}

class Router {
	private $p = null;

	public function __construct() {
		ob_start();
		session_start();

		register_shutdown_function('\zephyros\Cache::commit');
		register_shutdown_function('\ehbox\core\Cache::commit');

		if ( extension_loaded ('newrelic') ) {
			newrelic_set_appname( 'Ehbox:'.\Config::SUBDOMAIN );
		}

		$this->p = \zephyros\RouteParameters::init();

		if (isset($_POST["_method"])) {
			// POST -> UPDATE ~~~~~ PUT -> INSERT
			if ($_POST["_method"] != "POST" && $_POST["_method"] != "PUT" && $_POST["_method"] != "DELETE") {
				$this->__toError( \zephyros\Controller::ERROR_BAD_REQUEST );
			} else {
				$this->p->method = $_POST["_method"];
			}
		} else {
			$this->p->method = $_SERVER['REQUEST_METHOD'];
		}

		if ( substr($_SERVER['SERVER_NAME'], 0, 2) == 'm.' ) {
			$this->p->mobile = true;
		}

		if ( isset($_GET['controller']) ) {
			$this->p->controller = $_GET['controller'];
			$this->p->action = isset($_GET['action']) ? $_GET['action'] : '';
			$this->p->id = isset($_GET['id']) ? $_GET['id'] : 0;
		} else {
			$uri = substr($_SERVER["REQUEST_URI"], 1);
			if ( strpos($uri, "?") !== false ) {
				$uri = explode("?", $uri);
				$uri = $uri[0];
			}

			list($this->p->controller, $this->p->action, $this->p->id) = (array) array_pad(explode("/", $uri), 3, "");
		}

		foreach ( array('json', 'csv') as $format ) {
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

		if ( isset($_SESSION['user_id']) != null ) {
			$this->p->hasValidSession = true;
		}

		$this->route();
	}

	public function __toError( $code = 0 ) {
		$this->p->errorCode = $code;
		new \zephyros\Controller( $this->p );
		exit();
	}

	public function route() {
		if ( 
			isset(\Config::$enforce_ssl) && 
			\Config::$enforce_ssl && 
			isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 
			$_SERVER['HTTP_X_FORWARDED_PROTO'] != 'https' ) 
		{
			header('Location: https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
		}

		if ( $this->p->controller == "" ) {
			$this->p->controller = "homepage";
		}
		
		if ( !isset( \Config::$webservice) || ( isset( \Config::$webservice) && !\Config::$webservice) ) {
			if ( $this->p->format == 'html' && !file_exists( \Config::BASE_PATH.'/application/controllers/'.\Config::SUBDOMAIN.'/'.Inflector::camelize( $this->p->controller ).'.class.php' ) ) {
				$this->__toError( \zephyros\Controller::ERROR_NOT_FOUND );
			} else if ( !$this->p->hasValidSession && !in_array($this->p->controller, \Config::$doesntRequireAuthentication) ) {
				if ( $this->p->format == 'html' ) {
					header("Location: /".\Config::$doesntRequireAuthentication[0]); // Il primo elemento dell'array viene considerato quello a cui rimandare l'utente nel caso di sessione mancante
				} else {
					$__c = new \zephyros\Controller( $this->p );
					$__c->response( 'ERROR' );
					$this->__toError( \zephyros\Controller::ERROR_UNAUTHORIZED );
				}
				exit;
			}
		} else {
			if ( !in_array($this->p->controller, \Config::$doesntRequireAuthentication) && !$this->p->hasValidSession ) {
				$this->__toError( \zephyros\Controller::ERROR_UNAUTHORIZED );
			}

			if ($this->p->controller == "") {
				$this->__toError( \zephyros\Controller::ERROR_BAD_REQUEST );
			}
		}
		
		$className = \Config::NS.'\\controllers\\'.\Config::SUBDOMAIN.'\\'.Inflector::camelize( $this->p->controller );
		$__c = new $className($this->p);
		try {
			$__c->render();
		} catch ( \Exception $e) {
			if ( DEV_ENVIRONMENT ) {
				var_dump( $e );
			}
			$this->__toError( \zephyros\Controller::ERROR_UNEXPECTED );
		}

		$this->track_with_newrelic();
	}

	private function track_with_newrelic() {
		if (! extension_loaded ('newrelic') ) {
			return;
		}

		$controller = !empty($this->p->controller) ? $this->p->controller : 'undefined';
		$action = !empty($this->p->action) ? $this->p->action : 'default';

		newrelic_name_transaction( $controller."/".$action." (" . $this->p->method . ")" );
	}
}

$__r = new Router();