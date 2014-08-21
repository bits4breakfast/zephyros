<?php
namespace Bits4breakfast\Zephyros;

use Bits4breakfast\Zephyros\Exception\Http\NotFoundException;

final class AppLoader {

	private $p = null;
	private $environemnt = 'dev';
	private $app_base_path;

	private $controller = null;

	public function __construct($subdomain = 'Frontend') {
		ob_start();
		session_start();

		if (empty($subdomain)) {
			throw new \InvalidArgumentException( '$subdomain cannot be an empty string' );
		}

		$this->app_base_path = realpath(getcwd().'/../../..');

		$this->route = new Route;
		$this->route->subdomain = $subdomain;
	}

	public function boot() {
		if ( isset($_ENV['ZEPHYROS_APP_ENVIRONMENT']) ) {
			$this->environemnt = $_ENV['ZEPHYROS_APP_ENVIRONMENT'];
		} else {
			$this->environemnt = 'dev';
		}

		$config = new Config($this->app_base_path, $this->route->subdomain, $this->environemnt);
		$services = new Services($this->app_base_path);
		$container = ServiceContainer::init($config, $services);

		$router = new Router($this->route, $config);
		$controller = $router->route();

		if (file_exists($config->app_base_path.'/src/'.implode(DIRECTORY_SEPARATOR, $controller)).'.php') {
			$controller = implode('\\', $controller);
			$controller = new $controller($this->route, $container);
			$controller->render();
			$controller->shutdown();
		} else {
			\HttpResponse::status( 501 );
		}

		if (extension_loaded('newrelic')) {
			$this->track_with_newrelic();
		}
	}

	private function track_with_newrelic() {
		newrelic_set_appname( $this->route->subdomain );
		$controller = !empty($this->route->controller) ? $this->route->controller : 'undefined';
		$action = !empty($this->route->action) ? $this->route->action : 'default';
		newrelic_name_transaction( $controller."/".$action." (" . $this->route->method . ")" );
	}
}