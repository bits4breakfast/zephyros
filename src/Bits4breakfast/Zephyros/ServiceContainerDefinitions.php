<?php
namespace Bits4breakfast\Zephyros;

class ServiceContainerDefinitions implements ServiceContainerDefinitionsInterface {
	public $app_base_path = '';

	public $definitions = [];

	public function __construct($app_base_path) {
		$this->app_base_path = $app_base_path;
		$this->load();
	}

	public function load() {
		$key = $this->app_base_path.':service_container_definitions';
		if ( ($this->definitions = apc_fetch($key) ) === false ) {
			if ( file_exists($this->app_base_path.'/app/config/services.json') ) {
				$definitions = json_decode( file_get_contents($this->app_base_path.'/app/config/services.json'), true );
				foreach ($definitions as $key => $value) {
					$this->definitions[$key] = $value;
				}
			}

			apc_store($key, $this->definitions, 86400);
		}
	}

	public function get( $key ) {
		if ( isset($this->definitions[$key]) ) {
			return $this->definitions[$key];
		}

		return null;
	}

	public function dump() {
		return $this->definitions;
	}
}