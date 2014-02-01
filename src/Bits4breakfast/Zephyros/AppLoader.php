<?php
namespace Bits4breakfast\Zephyros;

use Bits4breakfast\Zephyros\RouteParameters;
use Bits4breakfast\Zephyros\Config;
use Bits4breakfast\Zephyros\ServiceContainer;

final class AppLoader {

	private $p = null;
	private $environemnt = 'dev';
	private $controller = null;

	public function __construct($subdomain = 'www') {
		ob_start();
		session_start();

		if (empty($subdomain)) {
			throw new \InvalidArgumentException( '$subdomain cannot be an empty string' );
		}

		$this->p = new Route;
		$this->p->subdomain = $subdomain;
	}

	public function boot() {
		if ( isset($_ENV['ZEPHYROS_APP_ENVIRONMENT']) ) {
			$this->environemnt = $_ENV['ZEPHYROS_APP_ENVIRONMENT'];
		} else {
			$_ENV['ZEPHYROS_APP_ENVIRONMENT'] = 'dev';
		}

		$config = new Config($this->p->subdomain);
		$container = new ServiceContainer($config);

		$router = new Router($this->p, $config);
		$controller = $router->route();

		if (! extension_loaded ('newrelic') ) {
			$this->track_with_newrelic();
		}
	}

	private function track_with_newrelic() {
		newrelic_set_appname( $this->p->subdomain );
		$controller = !empty($this->p->controller) ? $this->p->controller : 'undefined';
		$action = !empty($this->p->action) ? $this->p->action : 'default';
		newrelic_name_transaction( $controller."/".$action." (" . $this->p->method . ")" );
	}
}