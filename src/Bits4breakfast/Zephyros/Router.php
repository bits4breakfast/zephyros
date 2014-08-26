<?php
namespace Bits4breakfast\Zephyros;

use Bits4breakfast\Zephyros\Exception\Http\BadRequestException;

class Router {
	private $p = null;
	private $config = null;

	public function __construct(Route $p, Config $config) {
		$this->p = $p;
		$this->config = $config;
	}

	public function route() {
		if (isset($_POST["_method"])) {
			$this->p->method = $_POST["_method"];
		} else {
			$this->p->method = $_SERVER['REQUEST_METHOD'];
		}

		if ($this->p->method != "GET" && $this->p->method != "POST" && $this->p->method != "PUT" && $this->p->method != "DELETE") {
			throw new BadRequestException();
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

		foreach ( ['json', 'csv'] as $format ) {
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
				$this->p->id = @mysql_escape_string($this->p->id);
			}
		} else {
			$this->p->id = 0;
		}

		if ( $this->p->controller == "" ) {
			$this->p->controller = "home";
		}
		
		return [
			$this->config->get('kernel_namespace'),
			'Controller',
			ucfirst(strtolower($this->p->subdomain)),
			Inflector::camelize($this->p->controller)
		];
	}
}