<?php
/*
$Id$
*/
class Controller {
	protected $db = null;
	protected $p = null;
	protected $user = null;
	protected $response = null;

	const ERROR_BAD_REQUEST = 400;
	const ERROR_UNAUTHORIZED = 401;
	const ERROR_FORBIDDEN = 403;
	const ERROR_NOT_FOUND = 404;
	const ERROR_UNEXPECTED = 500;
	const ERROR_NOT_IMPLEMENTED = 501;

	public function __construct( RouteParameters $parameters ) {
		$this->p = $parameters;

		$this->db = Mysql::init();

		if ( $parameters->hasValidSession ) {
			$class = ( isset(Config::$logged_user_class) ? Config::$logged_user_class : 'User' );
			$this->user = $class::init( $_SESSION['userId'] );
		}

		$lang = "IT";
		if ( isset($_GET["lang"]) ) {
			$lang = $this->db->escape(strtoupper($_GET["lang"]));
			$_SESSION["_lang"] = $lang;
		} else if ( isset($_SESSION["_lang"]) ) {
			$lang = $this->db->escape(strtoupper($_SESSION["_lang"]));
		} else if ( isset($_POST["lang"]) ) {
			$lang = $this->db->escape(strtoupper($_POST["lang"]));
			$_SESSION["_lang"] = $lang;
		}
		$this->l = new LanguageManager($lang);

		if ( $this->p->format == 'json' ) {
			header("Content-type: text/json");
		}

		if ( $this->p->errorCode > 0 ) {
			$this->__toError( $this->p->errorCode );
		}
	}

	public function render() {
		try {
			$classMethods = get_class_methods($this);
			if ( in_array($this->p->action, $classMethods ) ) {
				$method = $this->p->action;
				$this->$method();
			} else if ( in_array("__default", $classMethods) ) {
				$this->__default();
			} else {
				$this->__toError(Controller::ERROR_UNEXPECTED);
			}
		} catch ( UserNotLoggedException $e ) {
			Logger::logException($e);
			$this->__toError( Controller::ERROR_UNAUTHORIZED );
		} catch ( SecurityException $e ) {
			Logger::logException($e);
			$this->__toError( Controller::ERROR_FORBIDDEN );
		} catch ( NonExistingItemException $e ) {
			Logger::logException($e);
			$this->__toError(Controller::ERROR_NOT_FOUND);
		} catch ( Exception $e ) {
			Logger::logException($e);
			$this->__toError(Controller::ERROR_UNEXPECTED);
		}
	}

	final protected function __default() {
		$classMethods = get_class_methods($this);

		if ( $this->p->action != '' && $this->p->id == '' ) {
			$this->p->id = $this->p->action;
			$this->p->action = '';
		}

		if ( $this->p->id === 0 || $this->p->id === '' ) {
			if ( $this->p->method == "GET" && in_array("index", $classMethods) ) {
				$this->p->method = 'index';
				$this->index();
			} else if ( ( $this->p->method == 'PUT' || $this->p->method == 'POST' ) && in_array("save", $classMethods) ) {
				$this->p->method = 'save';
				$this->save();
			} else {
				$this->__toError(Controller::ERROR_UNEXPECTED);
			}
		} else {
			if ( $this->p->method == "GET" && in_array("retrieve", $classMethods) ) {
				$this->p->method = 'retrieve';
				$this->retrieve();
			} else if ( $this->p->method == "DELETE" && in_array("delete", $classMethods) ) {
				$this->p->method = 'delete';
				$this->delete();
			} else if ( $this->p->method == 'POST' && in_array("save", $classMethods) ) {
				$this->p->method = 'save';
				$this->save();
			} else {
				$this->__toError(Controller::ERROR_UNEXPECTED);
			}
		}
	}

	protected function __toError( $errorCode = 0 ) {
		if ( $this->p->format == 'html' ) {
			$errUiPath = BaseConfig::BASE_PATH.'/application/interfaces/'.Config::SUBDOMAIN.'/ErrorsUI.class.php';
			if ( file_exists($errUiPath) ) {
				include $errUiPath;
				$this->response = new ErrorsUI( $errorCode, $this->user );
			}
		} else {
			switch ( $errorCode ) {
			default:
			case Controller::ERROR_BAD_REQUEST:
				header('HTTP/1.0 400 Bad Request');
				break;
			case Controller::ERROR_UNAUTHORIZED:
				header('HTTP/1.0 401 Unauthorized');
				break;
			case Controller::ERROR_NOT_FOUND:
				header('HTTP/1.0 404 Not Found');
				break;
			case Controller::ERROR_UNEXPECTED:
				header('HTTP/1.0 500 Internal Server Error');
				break;
			case Controller::ERROR_UNEXPECTED:
				header('HTTP/1.0 501 Not Implemented');
				break;
			}
			$this->response( 'ERROR' );
		}
	}

	protected function preventCaching() {
		header("ETag: PUB" . time());
		header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-1000) . " GMT");
		header("Expires: " . gmdate("D, d M Y H:i:s", time() - 100) . " GMT");
		header("Pragma: no-cache");
		header("Cache-Control: max-age=1, s-maxage=1, no-cache, must-revalidate");
	}

	final public function redirect_to( $controller = null, $action = null, $id = null, $parameters = null ) {
		$path = '/';
		
		if ( !empty($controller) ) {
			$path .= $controller;
			if ( !empty($action) ) {
				$path .= '/'.$action;
			}
			if ( !empty($id) ) {
				$path .= '/'.$id;
			}
		}
		if ( !empty($parameters) ) {
			$path .= '?'.http_build_query($parameters);
		}

		header('Location: '.$path);
	}

	public function logout() {
		$this->user = null;
		unset($_SESSION['userId']);
		$this->p->hasValidSession = false;
	}

	public function __destruct() {
		$this->preventCaching();
		if ( $this->p->format == 'json' ) {
			if ( $this->response === null ) {
				$this->response();
			}
			try {
				echo json_encode($this->response);
			} catch(Exception $e) {
				Logger::logException($e);
			}
		} else if ( $this->response !== null ) {
			if ( is_string($this->response) ) {
				echo $this->response;
			} else if ( is_object($this->response) && substr( get_class($this->response), -2 ) == 'UI' ) {
				$this->response->setLanguageManager( $this->l );
				$this->response->setRoute( $this->p );
				$this->response->setUser( $this->user );
				echo $this->response->output();
			} else if ( is_array($this->response) && !empty($this->response['code'])) {
				echo '<h1>Error: #'.$this->response['code'].'</h1>';
			}
		}
	}

	public function response($code = 'ACK', $message = null, $payload = null) {
		$this->response = array(
			'success' => ( $code == 'ACK' ),
			'errorCode' => $code,
			'errorMessage' => $message,
			'payload' => $payload
		);
	}
}
class UserNotLoggedException extends Exception {}
class SecurityException extends Exception {}
class InvalidActivationTokenException extends Exception {}
?>