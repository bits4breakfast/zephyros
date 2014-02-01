<?php
namespace Bits4breakfast\Zephyros;

final class AppLoader {

	private $p = null;
	private $environemnt = 'dev';
	private $app_base_path;

	private $controller = null;

	public function __construct($subdomain = 'www') {
		ob_start();
		session_start();

		if (empty($subdomain)) {
			throw new \InvalidArgumentException( '$subdomain cannot be an empty string' );
		}

		$this->app_base_path = realpath(getcwd().'/../..');

		$this->p = new Route;
		$this->p->subdomain = $subdomain;
	}

	public function boot() {
		if ( isset($_ENV['ZEPHYROS_APP_ENVIRONMENT']) ) {
			$this->environemnt = $_ENV['ZEPHYROS_APP_ENVIRONMENT'];
		} else {
			$this->environemnt = 'dev';
		}

		$config = new Config($this->app_base_path, $this->p->subdomain, $this->environemnt);
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