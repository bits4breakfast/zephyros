<?php
namespace bits4breakfast\zephyros;

class Controller {
	protected $db = null;
	protected $p = null;
	protected $user = null;
	protected $l = null;
	protected $response = null;

	const ERROR_BAD_REQUEST = 400;
	const ERROR_UNAUTHORIZED = 401;
	const ERROR_FORBIDDEN = 403;
	const ERROR_NOT_FOUND = 404;
	const ERROR_UNEXPECTED = 500;
	const ERROR_NOT_IMPLEMENTED = 501;

	public function __construct( RouteParameters $parameters ) {
		$this->p = $parameters;

		if ( $parameters->hasValidSession ) {
			$user_class = '\\'.\Config::NS.'\\model\\'.\Config::LOGGED_USER_CLASS;
			$this->user = $user_class::init( $_SESSION['user_id'] );
		}

		if ( $this->p->format == 'json' ) {
			header("Content-type: text/json");
		}

		if ( $this->p->errorCode > 0 ) {
			$this->__toError( $this->p->errorCode );
		}
		
		$lang = ( isset($_GET['lang']) && trim($_GET['lang']) != '' && strlen($_GET['lang']) == 2 ? $_GET['lang'] : 'en' );
		
		if ( isset(\Config::$allowed_languages) && !in_array($lang, \Config::$allowed_languages)) {
			$this->l = new \zephyros\LanguageManager( 'en' );
		} else {
			$this->l = new \ehbox\core\LanguageManager( $lang );
		}
	}

	public function render() {
		try {
			$class_methods = get_class_methods($this);
			if ( in_array($this->p->action, $class_methods ) ) {
				$this->db = Mysql::init();
				$method = $this->p->action;
				$this->$method();
			} else if ( in_array("__default", $class_methods) ) {
				$this->__default();
			} else {
				$this->__toError( self::ERROR_NOT_FOUND );
			}
		} catch ( \zephyros\UserNotLoggedException $e ) {
			$this->__toError( self::ERROR_UNAUTHORIZED );
		} catch ( \zephyros\SecurityException $e ) {
			$this->db->general_rollback();
			$this->__toError( self::ERROR_FORBIDDEN );
		} catch ( \zephyros\NonExistingItemException $e ) {
			$this->db->general_rollback();
			$this->__toError( self::ERROR_NOT_FOUND );
		} catch( \zephyros\InvalidRequestException $e ) {
			$this->db->general_rollback();
			$this->__toError( self::ERROR_BAD_REQUEST );
			$this->response( 'ERROR', $e->getMessage() );
		} catch ( \zephyros\BusinessLogicErrorException $e ) {
			$this->db->general_rollback();
			$this->response( 'ERROR', $e->getMessage() );
		} catch ( \Exception $e ) {
			$this->db->general_rollback();
			$this->__toError( self::ERROR_UNEXPECTED );
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
				$this->__toError( self::ERROR_NOT_FOUND );
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
				$this->__toError( self::ERROR_NOT_FOUND );
			}
		}
	}

	protected function __toError( $error_code = 0 ) {
		switch ( $error_code ) {
		default:
		case self::ERROR_BAD_REQUEST:
			header('HTTP/1.0 400 Bad Request');
			break;
		case self::ERROR_UNAUTHORIZED:
			header('HTTP/1.0 401 Unauthorized');
			break;
		case self::ERROR_NOT_FOUND:
			header('HTTP/1.0 404 Not Found');
			break;
		case self::ERROR_UNEXPECTED:
			header('HTTP/1.0 500 Internal Server Error');
			break;
		case self::ERROR_UNEXPECTED:
			header('HTTP/1.0 501 Not Implemented');
			break;
		}
			
		if ( $this->p->format == 'html' ) {
			$error_ui_path = \BaseConfig::BASE_PATH.'/application/ui/'.\Config::SUBDOMAIN.'/Errors.class.php';
			if ( file_exists($error_ui_path) ) {
				include $error_ui_path;
				$fully_qualified_name = '\\'.\Config::NS.'\\ui\\'.\Config::SUBDOMAIN.'\\Errors';
				$this->response = new $fully_qualified_name();
				$this->response->error_code = $error_code;
			}
		} else {
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
		unset( $_SESSION['user_id'] );
	}

	public function __destruct() {
		if ( $this->p->format == 'json' ) {
			if ( $this->response == null ) {
				$this->response();
			}
			try {
				echo json_encode($this->response);
			} catch( \Exception $e ) {
				Logger::log_exception($e);
			}
		} else if ( $this->response !== null ) {
			if ( is_string($this->response) ) {
				echo $this->response;
			} else if ( $this->response instanceof UserInterface ) {
				$this->response->setRoute( $this->p );
				$this->response->setLanguageManager( $this->l );
				$this->response->setUser( $this->user );
				echo $this->response->output();
			} else if ( is_array($this->response) && !empty($this->response['code'])) {
				echo '<h1>Error: #'.$this->response['code'].'</h1>';
			}
		}
	}

	public function response( $code = 'ACK', $payload = null ) {
		$this->response = [
			'success' => ( $code == 'ACK' ),
			'code' => $code,
			'payload' => $payload
		];
	}

	public function attach_csv( $titles = [], $feed = [] ) {
		header("Content-type: text/csv");
		header('Content-Disposition: attachment; filename="'.$this->p->controller.'-'.( $this->p->action != '' ? $this->p->action.'-' : '' ).@date("YmdHis").'.csv"');

		if ( is_array($feed) && isset($feed[0]) ) {
			$output = fopen("php://output", 'w');
			fputcsv( $output, $titles );
			
			foreach ( $feed as $line ) {
				$temp = [];
				foreach ( (array)$line as $value ) {
					if ( is_array($value) ) {
						$tempArray = [];
						foreach ( $value as $key => $details ) {
							if ( is_scalar($details) ) {
								$tempArray[] = $details;
							} else {
								$tempArray[] = implode( ': ', array_values($details) );
							}
						}
						$temp[] = implode( '; ', array_values($tempArray) );
						unset( $tempArray );
					} else {
						$temp[] = $value;
					}
				}
				fputcsv( $output, array_values($temp) );
			}
			fclose( $output );
		}
	}
}
class UserNotLoggedException extends \Exception {}
class SecurityException extends \Exception {}
class InvalidRequestException extends \Exception {}
class BusinessLogicErrorException extends \Exception {}