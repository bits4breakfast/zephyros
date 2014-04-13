<?php
namespace Bits4breakfast\Zephyros;

use Bits4breakst\Zephyros\Exception\Http\HttpException;
use Bits4breakst\Zephyros\Exception\Http\NotFoundException;
use Bits4breakst\Zephyros\Exception\Http\UnauthorizedException;
use Bits4breakst\Zephyros\Exception\Http\NotImplementedException;
use Bits4breakst\Zephyros\Exception\Http\InternalServerErrorException;

class Controller {
	public $config = null;
	public $container = null;
	public $route = null;

	protected $db = null;
	protected $l = null;
	protected $user = null;
	protected $response = null;

	public function __construct(Route $route, ServiceContainer $container) {
		$this->route = $route;

		$this->container = $container;
		$this->config = $container->config();
		$this->db = $container->db();
		$this->l = $container->lm();

		if (isset($_SESSION['user_id'])) {
			$user_class = $this->config->get('authentication.class');
			$this->user = $user_class::init( $_SESSION['user_id'] );
		}

		if ($this->route->format == 'json') {
			header("Content-type: text/json");
		}
		
		$lang = ( isset($_GET['lang']) && trim($_GET['lang']) != '' && strlen($_GET['lang']) == 2 ? $_GET['lang'] : 'en' );
		$allowed_languages = $this->config->get('kernel.allowed_languages');
		if ($allowed_languages === NULL || ($allowed_languages && !in_array($lang, $allowed_languages))) {
			$lang = 'en';
		}

		$this->l->set_language( $lang );
	}

	public function render() {
		try {
			if ($this->user === null && isset($this->requires_authentication) && $this->requires_authentication) {
				throw new UnauthorizedException;
			}

			$class_methods = get_class_methods($this);
			if (in_array($this->route->action, $class_methods)) {
				$response = $this->{$this->route->action}();
				if ( $this->response === null && $response !== null ) {
					$this->response('ACK', $response);
				}
			} else if (in_array("_default", $class_methods)) {
				$this->_default();
			} else {
				throw new NotFoundException();
			}
		} catch ( HttpException $e ) {
			$this->_render_error($e->getCode(), $e->getMessage(), $e->payload );
		} catch ( \Exception $e ) {
			$this->_render_error(500);
		}
	}

	final protected function _render_error( $error_code, $message = '', $payload = [] ) {
		$this->db->general_rollback();
		\HttpResponse::status( $error_code );
		if ( $this->route->format == 'html' ) {
			if ($this->container->config()->get('errors.rescue_page')) {
				$fully_qualified_name = '\\'.$this->container->config()->get('kernel.namespace').'\\UI\\'.ucfirst(strtolower($this->route->subdomain)).'\\ErrorPage';
				$this->response = new $fully_qualified_name();
				$this->response->error_code = $error_code;
				$this->response->message = $message;
				$this->response->payload = $payload;
				$this->generate_output();
			}
		} else {
			$this->response( 'ERROR', $payload );
		}
	}

	final protected function _default() {
		$class_methods = get_class_methods($this);

		if ($this->route->action != '' && $this->route->id == '') {
			$this->route->id = $this->route->action;
			$this->route->action = '';
		}

		if ($this->route->id === 0 || $this->route->id === '') {
			if ($this->route->method == "GET" && in_array("index", $class_methods)) {
				$this->route->method = 'index';
				$response = $this->index();
			} else if (($this->route->method == 'PUT' || $this->route->method == 'POST') && in_array("save", $class_methods)) {
				$this->route->method = 'save';
				$response = $this->save();
			} else {
				throw new NotImplementedException();
			}
		} else {
			if ($this->route->method == "GET" && in_array("retrieve", $class_methods)) {
				$this->route->method = 'retrieve';
				$response = $this->retrieve($this->route->id);
			} else if ($this->route->method == "DELETE" && in_array("delete", $class_methods)) {
				$this->route->method = 'delete';
				$response = $this->delete($this->route->id);
			} else if ($this->route->method == 'POST' && in_array("save", $class_methods)) {
				$this->route->method = 'save';
				$response = $this->save($this->route->id);
			} else {
				throw new NotImplementedException();
			}
		}

		if ( $this->response === null && $response !== null ) {
			$this->response('ACK', $response);
		}
	}

	protected function prevent_caching() {
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

	public final function shutdown() {
		if ($this->route->format == 'json') {
			if ( $this->response == null ) {
				$this->response();
			}
			echo json_encode($this->response);
		} else if ($this->response !== null) {
			if (is_string($this->response)) {
				echo $this->response;
			} else if ($this->response instanceof UserInterface) {
				$this->response->set_container( $this->container );
				$this->response->set_route( $this->route );
				$this->response->set_user( $this->user );
				echo $this->response->output();
			} else if (is_array($this->response) && !empty($this->response['code'])) {
				echo '<h1>Error: #'.$this->response['code'].'</h1>';
			}
		}
		Cache::commit();
	}

	public function response($code = 'ACK', $payload = null) {
		$this->response = [
			'success' => ( $code == 'ACK' ),
			'code' => $code,
			'payload' => $payload
		];
	}

	public function attach_csv($titles = [], $feed = []) {
		header("Content-type: text/csv");
		header('Content-Disposition: attachment; filename="'.$this->route->controller.'-'.( $this->route->action != '' ? $this->route->action.'-' : '' ).@date("YmdHis").'.csv"');

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