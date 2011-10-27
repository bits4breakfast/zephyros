<?php
/*
$Id$
*/
if ( getenv('ENVIRONMENT') == 'test' ) {
	define('TEST_ENVIRONMENT',true);
	define('PROD_ENVIRONMENT',false);
	error_reporting(E_ALL);
} else {
	define('TEST_ENVIRONMENT',false);
	define('PROD_ENVIRONMENT',true);
}

include BaseConfig::LIB.'/Controller.class.php';

function zephyros_class_loader( $className ) {
	if ( $className == 'Mysql' ) {
		include BaseConfig::BASE_PATH.'/library/Mysql.class.php';
	} else if ( $className == 'HttpReplicationClient' ) {
		include BaseConfig::BASE_PATH.'/library/Replica.class.php';
	} else if ( $className == 'UserInterface' ) {
		include BaseConfig::BASE_PATH.'/library/UserInterface.class.php';
	} else if ( substr($className, -2) == 'UI' ) {
		include BaseConfig::BASE_PATH.'/application/interfaces/'.Config::SUBDOMAIN.'/'.str_replace('_', '/', $className).'.class.php';
	} else {
		include BaseConfig::BASE_PATH.'/application/model/'.str_replace('_', '/', $className).'.class.php';
	}
}

spl_autoload_register( 'zephyros_class_loader' );

class RouteParameters {
	private static $instance = null;

	public $method = "GET";
	public $controller = "homepage";
	public $action = "__default";
	public $id = 0;
	public $format = "html";
	public $hasValidSession = false;
	public $mobile = false;
	
	public static function init() {
		if ( self::$instance == null ) {
			self::$instance = new RouteParameters();
		}
		return self::$instance;
	}
	
}

class Router {
	private $p = null;
	
	const ERROR_BAD_REQUEST = 400;
	const ERROR_UNAUTHORIZED = 401;
	const ERROR_NOT_FOUND = 404;
	
	public function __construct() {	
		ob_start();
		session_start();
		
		$this->p = RouteParameters::init();
		if (isset($_POST["_method"])) {
			// POST -> UPDATE ~~~~~ PUT -> INSERT
			if ($_POST["_method"] != "POST" && $_POST["_method"] != "PUT" && $_POST["_method"] != "DELETE") {
				$this->__toError(Router::ERROR_BAD_REQUEST);
			} else {
				$this->p->method = $_POST["_method"];
			}
		} else {
			$this->p->method = $_SERVER['REQUEST_METHOD'];
		}
		
		$uri = substr($_SERVER["REQUEST_URI"],1);
		if ( strpos($uri,"?") !== false ) {
			$uri = explode("?",$uri);
			$uri = $uri[0];
		}
		
		if ( substr($uri,0,2) == 'm/' ) {
			$this->p->mobile = true;
			$uri = substr($uri,2);
		}
		
		list($this->p->controller,$this->p->action,$this->p->id) = (array) array_pad(explode("/",$uri),3,"");
		
		
		foreach ( array('json','xml') as $format ) {
			if ( strpos($this->p->id,".".$format) !== false ) {
				$this->p->format = $format;
				$this->p->id = str_replace(".".$format,"",$this->p->id);
				break;
			} else if ( strpos($this->p->controller,".".$format) !== false ) {
				$this->p->format = $format;
				$this->p->controller = str_replace(".".$format,"",$this->p->controller);
				break;
			} else if ( strpos($this->p->action,".".$format) !== false ) {
				$this->p->format = $format;
				$this->p->action = str_replace(".".$format,"",$this->p->action);
				break;
			}
		}
		
		if ( is_numeric($this->p->action) ) {
			$this->p->id = (int) $this->p->action;
			$this->p->action = "";
		}
		
		if ( is_numeric($this->p->id) ) {
			$this->p->id = (int) $this->p->id;
		} else {
			$this->p->id = mysql_escape_string($this->p->id);
		}
		
		if ( !isset(Config::$webservice) || ( isset(Config::$webservice) && !Config::$webservice) ) {
			if ( isset($_SESSION["userId"]) && $_SESSION["userId"] != 0 ) {
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
		switch ( $code ) {
			default:
			case Router::ERROR_BAD_REQUEST:
				header('HTTP/1.0 400 Bad Request');
				break;
			case Router::ERROR_UNAUTHORIZED:
				header('HTTP/1.0 401 Unauthorized');
				break;
			case Router::ERROR_NOT_FOUND:
				header('HTTP/1.0 404 Not Found');
				break;
		}
		exit();
	}
	
	public function route() {
		if ( !isset(Config::$webservice) || ( isset(Config::$webservice) && !Config::$webservice) ) {
			if ( !$this->p->hasValidSession && !in_array($this->p->controller,Config::$doesntRequireAuthentication) ) {
				header("Location: /".Config::$doesntRequireAuthentication[0]); // Il primo elemento dell'array viene considerato quello a cui rimandare l'utente nel caso di sessione mancante
			}
			
			if ($this->p->controller == "") {
				$this->p->controller = "homepage";
			}
		} else {
			if ( !in_array($this->p->controller,Config::$doesntRequireAuthentication) && !$this->p->hasValidSession ) {
				$this->__toError(Router::ERROR_UNAUTHORIZED);
			}
			
			if ($this->p->controller == "") {
				$this->__toError(Router::ERROR_BAD_REQUEST);
			}
		}
		
		if ( file_exists(BaseConfig::BASE_PATH.'/application/controllers/'.Config::SUBDOMAIN.'/'.ucfirst($this->p->controller).'Controller.class.php') ) {
			include BaseConfig::BASE_PATH.'/application/controllers/'.Config::SUBDOMAIN.'/'.ucfirst($this->p->controller).'Controller.class.php';
			$className = ucfirst($this->p->controller)."Controller";
			$__c = new $className($this->p);
			$__c->render();
		} else {
			$this->__toError(Router::ERROR_NOT_FOUND);
		}
	}
}
$__r = new Router();
?>