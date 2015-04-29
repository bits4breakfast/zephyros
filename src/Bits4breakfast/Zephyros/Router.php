<?php
namespace Bits4breakfast\Zephyros;

use Bits4breakfast\Zephyros\Exception\Http\BadRequestException;

class Router {
	private $route = null;
	private $config = null;

	public function __construct(Route $route, Config $config) {
		$this->route = $route;
		$this->config = $config;
	}

	public function route() {
		if (isset($_POST["_method"])) {
			$this->route->method = $_POST["_method"];
		} else {
			$this->route->method = $_SERVER['REQUEST_METHOD'];
		}

		if ($this->route->method != "GET" && $this->route->method != "POST" && $this->route->method != "PUT" && $this->route->method != "DELETE") {
			throw new BadRequestException();
		}

		if (isset($_GET['controller'])) {
			$this->route->controller = $_GET['controller'];
			$this->route->action = isset($_GET['action']) ? $_GET['action'] : '';
			$this->route->id = isset($_GET['id']) ? $_GET['id'] : 0;
		} else {
			$uri = substr($_SERVER["REQUEST_URI"], 1);
			if (strpos($uri, "?") !== false) {
				$uri = explode("?", $uri);
				$uri = $uri[0];
			}

			list($this->route->controller, $this->route->action, $this->route->id) = (array) array_pad(explode("/", $uri), 3, "");
		}

		foreach (['json', 'csv'] as $format) {
			if (strpos($this->route->id, ".".$format) !== false) {
				$this->route->format = $format;
				$this->route->id = str_replace(".".$format, "", $this->route->id);
				break;
			} else if (strpos($this->route->controller, ".".$format) !== false) {
				$this->route->format = $format;
				$this->route->controller = str_replace(".".$format, "", $this->route->controller);
				break;
			} else if (strpos($this->route->action, ".".$format) !== false) {
				$this->route->format = $format;
				$this->route->action = str_replace(".".$format, "", $this->route->action);
				break;
			}
		}

		if (is_numeric($this->route->action)) {
			$this->route->id = (int) $this->route->action;
			$this->route->action = "";
		}

		if ($this->route->id != null) {
			if (is_numeric($this->route->id)) {
				$this->route->id = (int) $this->route->id;
			} else {
				$this->route->id = @mysql_escape_string($this->route->id);
			}
		} else {
			$this->route->id = 0;
		}

		if ($this->route->controller == "") {
			$this->route->controller = "home";
		}
		
		return [
			$this->config->get('kernel_namespace'),
			'Controller',
			ucfirst(strtolower($this->route->subdomain)),
			Inflector::camelize($this->route->controller)
		];
	}
}